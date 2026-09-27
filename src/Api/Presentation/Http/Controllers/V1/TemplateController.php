<?php

declare(strict_types=1);

namespace Ronda\Api\Presentation\Http\Controllers\V1;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Ronda\Api\Presentation\Http\Controllers\Concerns\HandlesApiRequests;
use Ronda\Api\Presentation\Http\Resources\TemplateResource;
use Ronda\Forms\Domain\Models\Template;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Plantillas y el esquema de su version vigente.
 * RONDA-PLAN-MAESTRO.md sec. 13.1 y ADR 0012
 *
 * Sin esto, una integracion no puede construir una entrega: necesita saber que
 * campos hay, de que tipo y cuales son obligatorios.
 */
final class TemplateController extends Controller
{
    use HandlesApiRequests;

    /**
     * Lista las plantillas.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Template::class);

        $plantillas = QueryBuilder::for(Template::class)
            ->allowedFilters(...[
                AllowedFilter::partial('name'),
                AllowedFilter::exact('code'),
                AllowedFilter::exact('status'),
            ])
            ->allowedSorts(...['name', 'code', 'created_at'])
            ->defaultSort('name')
            // `with()` devuelve el Builder de Eloquent y no el QueryBuilder,
            // asi que va DESPUES de todo lo del paquete: antes, cortaria la
            // cadena.
            ->with('currentVersion')
            ->cursorPaginate($this->perPage());

        return TemplateResource::collection($plantillas);
    }

    /**
     * Una plantilla con su esquema.
     */
    public function show(Template $template): TemplateResource
    {
        $this->authorize('view', $template);

        return new TemplateResource($template->load('currentVersion'));
    }
}
