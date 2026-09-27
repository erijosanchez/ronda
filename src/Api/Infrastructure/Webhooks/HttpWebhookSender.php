<?php

declare(strict_types=1);

namespace Ronda\Api\Infrastructure\Webhooks;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Ronda\Api\Domain\Exceptions\UnsafeWebhookUrl;
use Ronda\Api\Domain\Models\WebhookDelivery;
use Ronda\Api\Domain\Models\WebhookEndpoint;
use Ronda\Api\Domain\Webhooks\WebhookSender;
use Ronda\Api\Domain\Webhooks\WebhookUrl;

/**
 * Manda un aviso y anota como fue. RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * Tres cosas que no son opcionales:
 *
 * 1. FIRMA HMAC-SHA256 del cuerpo con el secreto del destino, en
 *    `X-Ronda-Signature`, mas la marca de tiempo en `X-Ronda-Timestamp`. Quien
 *    recibe puede comprobar que el aviso es nuestro y que no lo tocaron por el
 *    camino. La marca va DENTRO de lo firmado para que un aviso capturado no
 *    se pueda reenviar dias despues.
 *
 * 2. NO SE SIGUEN REDIRECCIONES. Es la puerta trasera del SSRF: una URL
 *    publica y limpia que responde 302 hacia `127.0.0.1` se saltaria toda la
 *    validacion que se hizo al guardarla.
 *
 * 3. Se vuelve a comprobar el destino AQUI, no solo al guardarlo. Un nombre
 *    valido hoy puede apuntar manana a una direccion interna, y entre que se
 *    guarda el webhook y se manda el aviso pasan dias.
 *
 * El tiempo de espera es corto a proposito: un destino lento no puede retener
 * un trabajador de la cola.
 */
final readonly class HttpWebhookSender implements WebhookSender
{
    public function __construct(
        private Http $http,
    ) {}

    public function __invoke(WebhookDelivery $delivery, ?CarbonImmutable $now = null): bool
    {
        $ahora = $now ?? CarbonImmutable::now('UTC');
        $destino = $delivery->endpoint;

        if (! $destino instanceof WebhookEndpoint) {
            return false;
        }

        $cuerpo = json_encode($delivery->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $marca = (string) $ahora->getTimestamp();

        try {
            // Se revalida el destino en el momento de llamar, no solo cuando
            // se guardo: un nombre puede cambiar de IP entre una cosa y otra.
            $url = WebhookUrl::fromString($destino->url, (bool) config('webhooks.allow_insecure', false))->value;
        } catch (UnsafeWebhookUrl $e) {
            return $this->fail($delivery, $destino, $ahora, $e->getMessage());
        }

        try {
            $respuesta = $this->http
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'Ronda-Webhooks/1',
                    'X-Ronda-Event' => $delivery->event,
                    'X-Ronda-Delivery' => (string) $delivery->getKey(),
                    'X-Ronda-Timestamp' => $marca,
                    'X-Ronda-Signature' => $this->sign($marca, $cuerpo, $destino->secret),
                ])
                ->timeout((int) config('webhooks.timeout', 10))
                ->withoutRedirecting()
                ->withBody($cuerpo, 'application/json')
                ->post($url);
        } catch (ConnectionException $e) {
            return $this->fail($delivery, $destino, $ahora, $e->getMessage());
        }

        if ($respuesta->successful()) {
            $delivery->forceFill([
                'status' => WebhookDelivery::DELIVERED,
                'attempts' => $delivery->attempts + 1,
                'response_status' => $respuesta->status(),
                'error' => null,
                'delivered_at' => $ahora,
                'next_attempt_at' => null,
            ])->save();

            $destino->forceFill([
                'last_success_at' => $ahora,
                'consecutive_failures' => 0,
            ])->save();

            return true;
        }

        return $this->fail(
            $delivery,
            $destino,
            $ahora,
            'HTTP '.$respuesta->status(),
            $respuesta->status(),
        );
    }

    /**
     * La firma: HMAC-SHA256 de «marca.cuerpo» con el secreto del destino.
     *
     * Se documenta tal cual para quien tenga que verificarla del otro lado.
     */
    public function sign(string $timestamp, string $body, string $secret): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    private function fail(
        WebhookDelivery $delivery,
        WebhookEndpoint $endpoint,
        CarbonImmutable $now,
        string $error,
        ?int $status = null,
    ): bool {
        $intentos = $delivery->attempts + 1;
        $quedanIntentos = $intentos < WebhookDelivery::MAX_ATTEMPTS;

        $delivery->forceFill([
            'status' => $quedanIntentos ? WebhookDelivery::PENDING : WebhookDelivery::FAILED,
            'attempts' => $intentos,
            'response_status' => $status,
            'error' => mb_substr($error, 0, 1000),
            'next_attempt_at' => $quedanIntentos
                ? $now->addSeconds($delivery->backoffSeconds($intentos))
                : null,
        ])->save();

        $fallosSeguidos = $endpoint->consecutive_failures + 1;

        $endpoint->forceFill([
            'last_failure_at' => $now,
            'consecutive_failures' => $fallosSeguidos,
            // Se apaga solo tras demasiados fallos seguidos: un destino muerto
            // no puede tener a Ronda llamandole para siempre.
            'is_active' => $fallosSeguidos < WebhookEndpoint::FAILURE_LIMIT,
        ])->save();

        return false;
    }
}
