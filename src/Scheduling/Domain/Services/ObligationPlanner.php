<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Domain\Services;

use Ronda\Scheduling\Domain\ValueObjects\PlannedObligation;
use Ronda\Scheduling\Domain\ValueObjects\Recurrence;
use Ronda\Scheduling\Domain\ValueObjects\SiteCalendar;
use Ronda\Scheduling\Domain\ValueObjects\TimeWindow;

/**
 * Decide que obligaciones tocan en un rango de fechas. ADR 0008.
 *
 * Es puro: recibe la regla, la ventana, las sedes y los feriados ya resueltos,
 * y devuelve lo que habria que materializar. No consulta la base ni escribe.
 * Por eso toda la logica de calendario —que es donde estan los errores caros—
 * se prueba sin base de datos.
 *
 * Reglas que aplica, en este orden, por sede y por fecha:
 *
 *   1. la fecha la da la regla, anclada al inicio de la programacion;
 *   2. fuera de [inicio, fin] de la programacion no hay obligacion;
 *   3. una sede cerrada o aun no abierta ese dia no tiene obligacion;
 *   4. si la programacion salta feriados y el dia es feriado, tampoco.
 *
 * Los feriados NO se materializan como obligaciones `excused`: simplemente no
 * se generan. `excused` queda para las excepciones decididas por una persona,
 * con motivo y responsable (sec. 9.3), que es lo que se audita.
 */
final class ObligationPlanner
{
    /**
     * @param  list<SiteCalendar>  $sites
     * @param  list<string>  $holidays  fechas Y-m-d
     * @return list<PlannedObligation>
     */
    public function plan(
        Recurrence $recurrence,
        TimeWindow $window,
        int $toleranceMinutes,
        bool $skipHolidays,
        string $startsOn,
        ?string $endsOn,
        array $sites,
        array $holidays,
        string $from,
        string $to,
    ): array {
        // El rango efectivo es la interseccion del pedido con la vigencia de la
        // programacion.
        $desde = max($from, $startsOn);
        $hasta = $endsOn === null ? $to : min($to, $endsOn);

        if ($desde > $hasta) {
            return [];
        }

        $fechas = $recurrence->datesBetween($startsOn, $desde, $hasta);
        $feriados = $skipHolidays ? array_flip($holidays) : [];

        $plan = [];

        foreach ($fechas as $fecha) {
            if (isset($feriados[$fecha])) {
                continue;
            }

            foreach ($sites as $site) {
                if (! $site->isOpenOn($fecha)) {
                    continue;
                }

                $instantes = $window->instantsOn($fecha, $site->timezone, $toleranceMinutes);

                $plan[] = new PlannedObligation(
                    siteId: $site->siteId,
                    occurrenceDate: $fecha,
                    opensAt: $instantes['opens_at'],
                    dueAt: $instantes['due_at'],
                    closesAt: $instantes['closes_at'],
                );
            }
        }

        return $plan;
    }
}
