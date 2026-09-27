<?php

declare(strict_types=1);

namespace Ronda\Api\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ronda\Platform\Domain\Contracts\PlanProvider;
use Ronda\Platform\Domain\PlanFeature;
use Symfony\Component\HttpFoundation\Response;

/**
 * La API es una funcion del plan. RONDA-PLAN-MAESTRO.md sec. 3.6 y 13.1
 *
 * Starter no la trae; Pro si. Se comprueba aqui, en la puerta, y no en cada
 * endpoint: un `if` por controlador es un `if` que alguien olvidara al añadir
 * el siguiente.
 *
 * Responde 403 con un cuerpo que dice que hacer. Un 404 seria mas discreto,
 * pero aqui no hay nada que ocultar —el sitio publico anuncia que Pro trae
 * API— y a quien esta integrando hay que decirle por que no entra.
 */
final readonly class EnsureApiIsInPlan
{
    public function __construct(
        private PlanProvider $plan,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->plan->allows(PlanFeature::Api)) {
            return $next($request);
        }

        return new JsonResponse([
            'message' => __('The API is not included in your plan.'),
            'code' => 'plan_without_api',
        ], 403);
    }
}
