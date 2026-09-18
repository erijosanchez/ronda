<?php

declare(strict_types=1);

namespace Ronda\Insights\Application\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Ronda\Insights\Application\Actions\RecalculateKpis;

/**
 * Recalcula los KPI recientes de UN tenant. RONDA-PLAN-MAESTRO.md sec. 9.6
 *
 * Mira unos dias hacia atras y no solo hoy: una revision que se aprueba el
 * jueves cambia la calidad del martes, y una justificacion cambia el
 * cumplimiento del dia que se justifico. Con la ventana, esas cifras se
 * corrigen solas en la siguiente pasada.
 */
final class RecalculateKpisJob implements ShouldQueue
{
    use Queueable;

    /** Dias hacia atras que se recalculan en cada pasada. */
    public const int DEFAULT_DAYS = 7;

    public function __construct(
        private readonly int $days = self::DEFAULT_DAYS,
    ) {}

    public function handle(RecalculateKpis $recalculate): void
    {
        $hoy = CarbonImmutable::now('UTC');

        // Un dia hacia adelante: la obligacion de una sede al este ya puede ser
        // de manana en UTC.
        $recalculate(
            $hoy->subDays(max(1, $this->days))->toDateString(),
            $hoy->addDay()->toDateString(),
        );
    }
}
