<?php

declare(strict_types=1);

namespace Ronda\Insights\Application\Queries;

use Illuminate\Database\Query\Builder;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Insights\Application\Data\KpiFilter;

/**
 * El punto de partida de toda consulta de KPI: `kpi_daily` recortado al
 * periodo, a la sede y a la plantilla que se estan mirando.
 *
 * Aqui vive la frontera por sede: `site_id` se limita a las sedes que el
 * usuario alcanza, con la subconsulta de `Site`, que lleva puesto
 * AssignedSitesScope. Sin esto, un encargado veria el cumplimiento del parque
 * entero en cuanto abriera el panel.
 */
final readonly class KpiQuery
{
    public function base(KpiFilter $filter): Builder
    {
        /** @var Builder $consulta */
        $consulta = Site::query()->getConnection()->table('kpi_daily');

        return $consulta
            ->whereBetween('kpi_date', [$filter->from, $filter->to])
            ->whereIn('site_id', Site::query()->select('sites.id')->toBase())
            ->when($filter->siteId !== null, fn (Builder $q): Builder => $q->where('site_id', $filter->siteId))
            ->when($filter->templateId !== null, fn (Builder $q): Builder => $q->where('template_id', $filter->templateId));
    }

    /**
     * Las sumas que componen todos los indicadores.
     *
     * @return list<string>
     */
    public function sums(): array
    {
        return [
            'fulfilled', 'missed', 'excused',
            'on_time', 'late', 'minutes_late_sum',
            'approved', 'rejected', 'approved_first_try',
            'reviews_resolved', 'review_minutes_sum',
        ];
    }
}
