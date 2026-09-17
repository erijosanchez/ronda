<?php

declare(strict_types=1);

namespace Ronda\Notifications\Presentation\Console;

use Illuminate\Console\Command;
use Ronda\Notifications\Application\Jobs\RunSlaChecksJob;
use Ronda\Platform\Domain\Models\Tenant;

/**
 * Lanza el repaso de SLA en todo el parque.
 *
 * Un job por tenant, como la materializacion: un cliente con un problema no
 * puede dejar al resto sin avisos, y cada job se reintenta por separado.
 */
final class SlaChecksCommand extends Command
{
    protected $signature = 'notifications:sla {--sync : Ejecutar en el acto, sin cola}';

    protected $description = 'Manda recordatorios y escala lo incumplido y lo no revisado en todos los tenants';

    public function handle(): int
    {
        $sync = (bool) $this->option('sync');
        $total = 0;

        Tenant::query()->each(function (Tenant $tenant) use ($sync, &$total): void {
            $tenant->run(function () use ($sync): void {
                $sync
                    ? dispatch_sync(new RunSlaChecksJob)
                    : dispatch(new RunSlaChecksJob);
            });

            $total++;
        });

        $this->info("Repaso de SLA lanzado en {$total} tenant(s).");

        return self::SUCCESS;
    }
}
