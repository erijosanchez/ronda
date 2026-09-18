<?php

declare(strict_types=1);

namespace Ronda\Insights\Application\Data;

/**
 * Que trozo de los KPI se esta mirando. RONDA-PLAN-MAESTRO.md sec. 9.6
 *
 * Las fechas son dias de sede (`Y-m-d`), como el grano de `kpi_daily`: el
 * tablero habla del calendario del local, no de UTC.
 */
final readonly class KpiFilter
{
    public function __construct(
        public string $from,
        public string $to,
        public ?int $siteId = null,
        public ?int $templateId = null,
    ) {}
}
