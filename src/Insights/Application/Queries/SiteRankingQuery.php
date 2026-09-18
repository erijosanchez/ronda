<?php

declare(strict_types=1);

namespace Ronda\Insights\Application\Queries;

use Illuminate\Pagination\LengthAwarePaginator;
use Ronda\Insights\Application\Data\KpiFilter;
use Ronda\Insights\Domain\ValueObjects\KpiSummary;

/**
 * El ranking de sedes del periodo. RONDA-PLAN-MAESTRO.md sec. 9.6
 *
 * Ordenado por cumplimiento ASCENDENTE: arriba las sedes que peor van, que son
 * las que hay que mirar. Un ranking que empieza por las que cumplen se lee
 * bonito y no sirve para nada.
 *
 * Las sedes sin nada que entregar en el periodo no salen: no tienen
 * cumplimiento, y colocarlas en cero o en cien seria inventarselo.
 *
 * Pagina (regla 5).
 */
final readonly class SiteRankingQuery
{
    public function __construct(
        private KpiQuery $kpi,
    ) {}

    /**
     * @return LengthAwarePaginator<int, array{site_id: int, site_name: string, summary: KpiSummary}>
     */
    public function __invoke(KpiFilter $filter, int $perPage = 10, string $pageName = 'ranking'): LengthAwarePaginator
    {
        $consulta = $this->kpi->base($filter)
            ->join('sites', 'sites.id', '=', 'kpi_daily.site_id')
            ->select('kpi_daily.site_id', 'sites.name as site_name')
            ->groupBy('kpi_daily.site_id', 'sites.name')
            ->havingRaw('sum(fulfilled) + sum(missed) > 0')
            ->orderByRaw('sum(fulfilled)::numeric / nullif(sum(fulfilled) + sum(missed), 0) asc')
            ->orderBy('sites.name');

        foreach ($this->kpi->sums() as $columna) {
            $consulta->selectRaw("coalesce(sum({$columna}), 0) as {$columna}");
        }

        $pagina = $consulta->paginate($perPage, ['*'], $pageName);

        return $pagina->through(static function (object $fila): array {
            /** @var array<string, int|string|null> $datos */
            $datos = (array) $fila;

            return [
                'site_id' => (int) $datos['site_id'],
                'site_name' => (string) $datos['site_name'],
                'summary' => KpiSummary::fromRow($datos),
            ];
        });
    }
}
