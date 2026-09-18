<?php

declare(strict_types=1);

namespace Ronda\Insights\Application\Queries;

use Ronda\Insights\Application\Data\KpiFilter;
use Ronda\Insights\Domain\ValueObjects\KpiSummary;

/**
 * Los indicadores del periodo, de una sola consulta.
 * RONDA-PLAN-MAESTRO.md sec. 9.6 y 13
 *
 * Suma filas ya calculadas; no toca `submissions` ni `obligations`. Es lo que
 * mantiene el tablero por debajo de los 800 ms del presupuesto de rendimiento.
 */
final readonly class KpiSummaryQuery
{
    public function __construct(
        private KpiQuery $kpi,
    ) {}

    public function __invoke(KpiFilter $filter): KpiSummary
    {
        $consulta = $this->kpi->base($filter);

        foreach ($this->kpi->sums() as $columna) {
            $consulta->selectRaw("coalesce(sum({$columna}), 0) as {$columna}");
        }

        $fila = $consulta->first();

        return $fila === null ? new KpiSummary : KpiSummary::fromRow((array) $fila);
    }
}
