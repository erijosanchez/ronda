<?php

declare(strict_types=1);

namespace Ronda\Api\Application\Actions;

use Illuminate\Contracts\Bus\Dispatcher;
use Ronda\Api\Application\Jobs\SendWebhookJob;
use Ronda\Api\Domain\Models\WebhookDelivery;

/**
 * Reenvia a mano una entrega. RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * El plan lo pide explicitamente, y el caso de uso es real: el servidor del
 * cliente estuvo caido una tarde y quiere recuperar los avisos de esa tarde sin
 * tener que provocar los eventos otra vez.
 *
 * Se manda EL MISMO cuerpo que se guardo, no uno nuevo: el aviso describe lo
 * que paso entonces, y el reporte puede haber cambiado desde entonces.
 *
 * Reenviar reinicia los intentos y limpia el error, pero NO borra el historial:
 * `attempts` queda a cero y lo anterior sigue en el registro de la fila.
 */
final readonly class ResendWebhookDelivery
{
    public function __construct(
        private Dispatcher $bus,
    ) {}

    public function __invoke(WebhookDelivery $delivery): WebhookDelivery
    {
        $delivery->forceFill([
            'status' => WebhookDelivery::PENDING,
            'attempts' => 0,
            'error' => null,
            'response_status' => null,
            'next_attempt_at' => null,
        ])->save();

        $this->bus->dispatch(new SendWebhookJob((int) $delivery->getKey()));

        return $delivery;
    }
}
