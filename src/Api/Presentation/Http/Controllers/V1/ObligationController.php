<?php

declare(strict_types=1);

namespace Ronda\Api\Presentation\Http\Controllers\V1;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Ronda\Api\Presentation\Http\Controllers\Concerns\HandlesApiRequests;
use Ronda\Api\Presentation\Http\Resources\ObligationResource;
use Ronda\Scheduling\Domain\Models\Obligation;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Lo que cada sede debe entregar. RONDA-PLAN-MAESTRO.md sec. 13.1 y ADR 0008
 *
 * Es la coleccion que mas crece —una fila por sede, plantilla y dia—, asi que
 * va por cursor: pedir la pagina 900 con OFFSET obligaria a PostgreSQL a
 * recorrer todo lo anterior cada vez.
 */
final class ObligationController extends Controller
{
    use HandlesApiRequests;

    /**
     * Lista las obligaciones.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Obligation::class);

        $obligaciones = QueryBuilder::for(Obligation::class)
            ->allowedFilters(...[
                AllowedFilter::exact('site_id'),
                AllowedFilter::exact('template_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('occurrence_date'),
                // El caso de uso real de una integracion: «dame lo de esta
                // semana», no «dame todo desde 2026».
                AllowedFilter::callback('from', fn ($query, $valor) => $query->whereDate('occurrence_date', '>=', $valor)),
                AllowedFilter::callback('to', fn ($query, $valor) => $query->whereDate('occurrence_date', '<=', $valor)),
            ])
            ->allowedSorts(...['occurrence_date', 'due_at'])
            ->defaultSort('-occurrence_date')
            ->cursorPaginate($this->perPage());

        return ObligationResource::collection($obligaciones);
    }
}
