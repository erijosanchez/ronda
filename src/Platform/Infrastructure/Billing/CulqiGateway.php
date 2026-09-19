<?php

declare(strict_types=1);

namespace Ronda\Platform\Infrastructure\Billing;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Ronda\Platform\Domain\Billing\ChargeRequest;
use Ronda\Platform\Domain\Billing\ChargeResult;
use Ronda\Platform\Domain\Contracts\BillingGateway;
use Ronda\Platform\Domain\Exceptions\BillingGatewayFailed;

/**
 * Cobro con tarjeta a traves de Culqi. RONDA-PLAN-MAESTRO.md sec. 15.2
 *
 * Usa la API v2 (`https://api.culqi.com/v2`) con la llave secreta como Bearer.
 * Solo tres recursos, y a proposito:
 *
 *   - `/customers`  el cliente, para poder guardarle una tarjeta
 *   - `/cards`      la tarjeta guardada, a partir de un token del navegador
 *   - `/charges`    el cobro, por el importe que calcula Ronda
 *
 * NO se usan `/plans` ni `/subscriptions`: son de monto fijo, y Ronda cobra por
 * sede activa, asi que el importe cambia cada periodo. Quien decide cuanto se
 * cobra es Ronda; Culqi solo ejecuta.
 *
 * Los datos de la tarjeta nunca pasan por este servidor: el navegador los
 * cambia por un token contra Culqi y aqui solo llega ese token (sec. 10.4).
 *
 * Una tarjeta rechazada vuelve como ChargeResult, no como excepcion: es un
 * resultado normal del negocio. Las excepciones quedan para lo que hay que ir
 * a mirar.
 */
final readonly class CulqiGateway implements BillingGateway
{
    public function __construct(
        private Http $http,
        private string $secretKey,
        private string $baseUrl,
        private int $timeout = 30,
    ) {}

    public function name(): string
    {
        return 'culqi';
    }

    public function storeCard(string $token, string $email, string $reference): string
    {
        $cliente = $this->post('/customers', [
            'email' => $email,
            // Culqi exige estos campos; se mandan neutros porque Ronda no pide
            // datos personales para cobrar: el cliente es una EMPRESA.
            'first_name' => mb_substr($reference, 0, 50),
            'last_name' => 'Ronda',
            'address' => 'No especificada',
            'address_city' => 'Lima',
            'country_code' => 'PE',
            'phone_number' => '000000000',
            'metadata' => ['reference' => $reference],
        ]);

        $customerId = $cliente['id'] ?? null;

        if (! is_string($customerId)) {
            throw BillingGatewayFailed::unexpected($this->name(), 'customer without id');
        }

        $tarjeta = $this->post('/cards', [
            'customer_id' => $customerId,
            'token_id' => $token,
        ]);

        $cardId = $tarjeta['id'] ?? null;

        if (! is_string($cardId)) {
            throw BillingGatewayFailed::unexpected($this->name(), 'card without id');
        }

        return $cardId;
    }

    public function charge(ChargeRequest $request): ChargeResult
    {
        try {
            $respuesta = $this->request()->post($this->baseUrl.'/charges', [
                // En centimos enteros: Culqi no acepta decimales.
                'amount' => $request->amount->cents(),
                'currency_code' => $request->amount->currency,
                'email' => $request->email,
                'source_id' => $request->source,
                'description' => mb_substr($request->description, 0, 80),
                'metadata' => [...$request->metadata, 'reference' => $request->reference],
            ]);
        } catch (ConnectionException $e) {
            throw BillingGatewayFailed::unreachable($this->name(), $e->getMessage());
        }

        // Un rechazo llega como 4xx con su motivo. Es informacion, no un fallo.
        if ($this->isDecline($respuesta)) {
            return ChargeResult::declined($this->declineReason($respuesta));
        }

        if ($respuesta->failed()) {
            throw BillingGatewayFailed::http($this->name(), $respuesta->status(), $respuesta->body());
        }

        $id = $respuesta->json('id');

        if (! is_string($id)) {
            throw BillingGatewayFailed::unexpected($this->name(), 'charge without id');
        }

        return ChargeResult::paid($id);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        try {
            $respuesta = $this->request()->post($this->baseUrl.$path, $payload);
        } catch (ConnectionException $e) {
            throw BillingGatewayFailed::unreachable($this->name(), $e->getMessage());
        }

        if ($respuesta->failed()) {
            throw BillingGatewayFailed::http($this->name(), $respuesta->status(), $respuesta->body());
        }

        $cuerpo = $respuesta->json();

        return is_array($cuerpo) ? $cuerpo : [];
    }

    private function request(): PendingRequest
    {
        return $this->http
            ->withToken($this->secretKey)
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout);
    }

    /**
     * Culqi responde los rechazos con 4xx y un objeto de error que trae
     * `user_message`: el texto pensado para ensenarselo a la persona.
     */
    private function isDecline(Response $response): bool
    {
        if (! $response->clientError()) {
            return false;
        }

        $tipo = $response->json('type');

        return is_string($tipo) && str_contains($tipo, 'card_error');
    }

    private function declineReason(Response $response): string
    {
        $mensaje = $response->json('user_message') ?? $response->json('merchant_message');

        return is_string($mensaje) && $mensaje !== ''
            ? $mensaje
            : __('The card was declined.');
    }
}
