<?php

declare(strict_types=1);

namespace Ronda\Insights\Application\Queries;

use Ronda\Insights\Application\Data\KpiFilter;

/**
 * Reincidencia: sedes que incumplen LA MISMA plantilla varias veces en el
 * periodo. RONDA-PLAN-MAESTRO.md sec. 9.6
 *
 * Es distinto del ranking: una sede puede tener buen cumplimiento global y aun
 * asi no mandar nunca el mismo reporte. Eso es un problema concreto, con
 * nombre, y se arregla hablando con alguien.
 *
 * Lista corta por definicion (las que pasan el umbral), asi que se limita en
 * vez de paginar.
 */
final readonly class RepeatOffendersQuery
{
    public function __construct(
        private KpiQuery $kpi,
    ) {}

    /**
     * @return list<array{site_name: string, template_name: string, missed: int}>
     */
    public function __invoke(KpiFilter $filter, int $threshold = 3, int $limit = 10): array
    {
        $filas = $this->kpi->base($filter)
            ->join('sites', 'sites.id', '=', 'kpi_daily.site_id')
            ->join('templates', 'templates.id', '=', 'kpi_daily.template_id')
            ->selectRaw('sites.name as site_name, templates.name as template_name, sum(missed) as missed')
            ->groupBy('sites.name', 'templates.name')
            ->havingRaw('sum(missed) >= ?', [$threshold])
            ->orderByRaw('sum(missed) desc')
            ->orderBy('sites.name')
            ->limit($limit)
            ->get();

        return array_values($filas->map(static function (object $fila): array {
            /** @var array<string, int|string|null> $datos */
            $datos = (array) $fila;

            return [
                'site_name' => (string) $datos['site_name'],
                'template_name' => (string) $datos['template_name'],
                'missed' => (int) $datos['missed'],
            ];
        })->all());
    }
}
