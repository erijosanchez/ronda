<?php

declare(strict_types=1);

namespace Ronda\Api\Application\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Ronda\Api\Domain\Models\WebhookDelivery;
use Ronda\Api\Domain\Webhooks\WebhookSender;

/**
 * Manda un aviso pendiente. RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * En cola, siempre: el cliente que entrega un reporte no tiene por que esperar
 * a que responda el servidor de otra empresa.
 *
 * El job NO reintenta por su cuenta (`tries = 1`). El reintento lo lleva la
 * fila de `webhook_deliveries` con su `next_attempt_at`, porque asi es
 * consultable —el plan pide registro y reenvio manual— y sobrevive a un
 * reinicio de la cola.
 */
final class SendWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(private readonly int $deliveryId) {}

    public function handle(WebhookSender $send): void
    {
        $entrega = WebhookDelivery::query()->with('endpoint')->find($this->deliveryId);

        if ($entrega instanceof WebhookDelivery && $entrega->status === WebhookDelivery::PENDING) {
            $send($entrega);
        }
    }
}
