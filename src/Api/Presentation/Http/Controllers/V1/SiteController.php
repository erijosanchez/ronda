<?php

declare(strict_types=1);

namespace Ronda\Api\Presentation\Http\Controllers\V1;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Ronda\Api\Presentation\Http\Controllers\Concerns\HandlesApiRequests;
use Ronda\Api\Presentation\Http\Resources\SiteResource;
use Ronda\Directory\Domain\Models\Site;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Sedes del cliente. RONDA-PLAN-MAESTRO.md sec. 13.1
 *
 * Los filtros y el orden van en LISTA BLANCA (`allowedFilters`): dejar filtrar
 * por cualquier columna convierte la API en un motor de consultas sobre la
 * base del cliente, y basta con un `?filter[password]=` para descubrirlo.
 *
 * No hace falta filtrar por las sedes de quien llama: el modelo lleva
 * AssignedSitesScope, asi que un token de un encargado ve las suyas y ya.
 */
final class SiteController extends Controller
{
    use HandlesApiRequests;

    /**
     * Lista las sedes.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Site::class);

        $sedes = QueryBuilder::for(Site::class)
            // Se desempaquetan con `...` porque el paquete los recibe como
            // argumentos variables, no como un array.
            ->allowedFilters(...[
                AllowedFilter::partial('name'),
                AllowedFilter::exact('code'),
                AllowedFilter::exact('zone_id'),
            ])
            ->allowedSorts(...['name', 'code', 'created_at'])
            ->allowedIncludes(...['zone'])
            ->defaultSort('name')
            ->cursorPaginate($this->perPage());

        return SiteResource::collection($sedes);
    }

    /**
     * Una sede.
     */
    public function show(Site $site): SiteResource
    {
        $this->authorize('view', $site);

        return new SiteResource($site->load('zone'));
    }
}
