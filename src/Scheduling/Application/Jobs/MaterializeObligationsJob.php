<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Application\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Ronda\Scheduling\Application\Actions\MarkMissedObligations;
use Ronda\Scheduling\Application\Actions\MaterializeObligations;
use Ronda\Scheduling\Domain\Models\Schedule;

/**
 * El trabajo diario del motor, para UN tenant. ADR 0008.
 *
 * Primero cierra lo vencido y despues materializa lo que viene. En ese orden:
 * al reves, una obligacion recien creada con cierre en el pasado (una
 * programacion dada de alta a media tarde) se marcaria como incumplida en la
 * misma pasada sin que la sede llegara a verla.
 *
 * Se despacha dentro del contexto de cada tenant; QueueTenancyBootstrapper se
 * encarga de que el worker vuelva a entrar en ese contexto.
 */
final class MaterializeObligationsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Dias hacia adelante que se materializan. Suficiente para que la lista de
     * pendientes y los recordatorios previos (sec. 9.3) tengan de donde tirar,
     * y corto para que un cambio en la programacion se note pronto.
     */
    public const int HORIZON_DAYS = 14;

    public function handle(MaterializeObligations $materialize, MarkMissedObligations $markMissed): void
    {
        $markMissed();

        // Se empieza un dia antes de hoy en UTC: por la noche en Lima ya es
        // manana en UTC, y sin este margen el dia local en curso se quedaria sin
        // materializar. Duplicar es imposible por la restriccion unica.
        $hoy = CarbonImmutable::now('UTC');
        $desde = $hoy->subDay()->toDateString();
        $hasta = $hoy->addDays(self::HORIZON_DAYS)->toDateString();

        Schedule::query()
            ->where('active', true)
            ->each(function (Schedule $schedule) use ($materialize, $desde, $hasta): void {
                $materialize($schedule, $desde, $hasta);
            });
    }
}
