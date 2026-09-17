<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Presentation\Console;

use Illuminate\Console\Command;
use Ronda\Platform\Domain\Models\Tenant;
use Ronda\Scheduling\Application\Jobs\MaterializeObligationsJob;

/**
 * Lanza la materializacion diaria en todo el parque. ADR 0008.
 *
 * Un job por tenant y no uno para todos: un cliente con una programacion rota
 * no puede impedir que el resto reciba sus obligaciones, y cada job se reintenta
 * por separado.
 */
final class MaterializeObligationsCommand extends Command
{
    protected $signature = 'obligations:materialize {--sync : Ejecutar en el acto, sin cola}';

    protected $description = 'Materializa las obligaciones de los proximos dias en todos los tenants';

    public function handle(): int
    {
        $sync = (bool) $this->option('sync');
        $total = 0;

        Tenant::query()->each(function (Tenant $tenant) use ($sync, &$total): void {
            $tenant->run(function () use ($sync): void {
                $sync
                    ? dispatch_sync(new MaterializeObligationsJob)
                    : dispatch(new MaterializeObligationsJob);
            });

            $total++;
        });

        $this->info("Materializacion lanzada en {$total} tenant(s).");

        return self::SUCCESS;
    }
}
