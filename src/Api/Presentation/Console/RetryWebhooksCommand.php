<?php

declare(strict_types=1);

namespace Ronda\Api\Presentation\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Bus\Dispatcher;
use Ronda\Api\Application\Jobs\SendWebhookJob;
use Ronda\Api\Domain\Models\WebhookDelivery;
use Ronda\Platform\Domain\Models\Tenant;

/**
 * Reintenta los avisos que tocan. RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * El reintento vive en la BASE y no en la cola: asi sobrevive a un reinicio,
 * es consultable —el plan pide registro— y se puede reenviar a mano. Este
 * comando es lo que lo hace avanzar.
 *
 * Recorre los clientes uno a uno: las entregas viven en la base de cada uno.
 */
final class RetryWebhooksCommand extends Command
{
    protected $signature = 'webhooks:retry';

    protected $description = 'Reintenta las entregas de webhook que ya tocan';

    public function handle(Dispatcher $bus): int
    {
        $ahora = CarbonImmutable::now('UTC');
        $total = 0;

        Tenant::query()->each(function (Tenant $tenant) use ($bus, $ahora, &$total): void {
            if (! $tenant->isReady()) {
                return;
            }

            $tenant->run(function () use ($bus, $ahora, &$total): void {
                WebhookDelivery::query()
                    ->where('status', WebhookDelivery::PENDING)
                    ->whereNotNull('next_attempt_at')
                    ->where('next_attempt_at', '<=', $ahora)
                    ->each(function (WebhookDelivery $entrega) use ($bus, &$total): void {
                        $bus->dispatch(new SendWebhookJob((int) $entrega->getKey()));
                        $total++;
                    });
            });
        });

        $this->info("Reintentos encolados: {$total}.");

        return self::SUCCESS;
    }
}
