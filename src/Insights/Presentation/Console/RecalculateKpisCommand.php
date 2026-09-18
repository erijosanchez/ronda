<?php

declare(strict_types=1);

namespace Ronda\Insights\Presentation\Console;

use Illuminate\Console\Command;
use Ronda\Insights\Application\Jobs\RecalculateKpisJob;
use Ronda\Platform\Domain\Models\Tenant;

/**
 * Recalcula los KPI de todo el parque.
 *
 * Un job por tenant, como el resto de repasos: el problema de un cliente no
 * deja al resto sin cifras.
 */
final class RecalculateKpisCommand extends Command
{
    protected $signature = 'kpi:recalculate {--days= : Dias hacia atras} {--sync : Ejecutar en el acto, sin cola}';

    protected $description = 'Recalcula los indicadores de cumplimiento en todos los tenants';

    public function handle(): int
    {
        $dias = (int) ($this->option('days') ?? RecalculateKpisJob::DEFAULT_DAYS);
        $sync = (bool) $this->option('sync');
        $total = 0;

        Tenant::query()->each(function (Tenant $tenant) use ($dias, $sync, &$total): void {
            $tenant->run(function () use ($dias, $sync): void {
                $sync
                    ? dispatch_sync(new RecalculateKpisJob($dias))
                    : dispatch(new RecalculateKpisJob($dias));
            });

            $total++;
        });

        $this->info("Recalculo de KPI lanzado en {$total} tenant(s).");

        return self::SUCCESS;
    }
}
