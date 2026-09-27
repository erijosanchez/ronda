<?php

declare(strict_types=1);

namespace Ronda\Api\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use Ronda\Api\Application\Jobs\SendWebhookJob;
use Ronda\Api\Domain\Models\WebhookDelivery;
use Ronda\Api\Domain\Models\WebhookEndpoint;
use Ronda\Api\Domain\Webhooks\WebhookEvent;
use Ronda\Platform\Domain\Contracts\PlanProvider;
use Ronda\Platform\Domain\PlanFeature;

/**
 * Anota un aviso para cada destino suscrito y lo encola.
 * RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * Primero se ANOTA y despues se encola, no al reves: si el proceso muriera
 * entre las dos cosas, queda una entrega pendiente que el comando de
 * reintentos recoge. Al reves quedaria un aviso perdido del que nadie sabe.
 *
 * Los destinos apagados —a mano o por acumular fallos— no reciben nada, y
 * tampoco se les anota una entrega: llenar el registro de avisos que nadie va
 * a mandar solo estorba para leer el registro.
 *
 * Los webhooks son del plan, como la API (sec. 3.6). Se comprueba AQUI y no en
 * la pantalla: si el cliente baja de plan, los destinos que ya registro siguen
 * en su base, y sin esta puerta seguirian recibiendo avisos.
 */
final readonly class QueueWebhook
{
    public function __construct(
        private Dispatcher $bus,
        private PlanProvider $plan,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return int cuantos destinos recibiran el aviso
     */
    public function __invoke(WebhookEvent $event, array $payload, ?CarbonImmutable $now = null): int
    {
        if (! $this->plan->allows(PlanFeature::Api)) {
            return 0;
        }

        $ahora = $now ?? CarbonImmutable::now('UTC');
        $encolados = 0;

        $destinos = WebhookEndpoint::query()
            ->where('is_active', true)
            ->get()
            ->filter(static fn (WebhookEndpoint $destino): bool => $destino->listensTo($event));

        foreach ($destinos as $destino) {
            $entrega = WebhookDelivery::query()->create([
                'webhook_endpoint_id' => $destino->getKey(),
                'event' => $event->value,
                'payload' => [
                    'event' => $event->value,
                    'sent_at' => $ahora->toIso8601String(),
                    'data' => $payload,
                ],
                'status' => WebhookDelivery::PENDING,
                'attempts' => 0,
            ]);

            $this->bus->dispatch(new SendWebhookJob((int) $entrega->getKey()));
            $encolados++;
        }

        return $encolados;
    }
}
