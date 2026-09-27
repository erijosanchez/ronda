<?php

declare(strict_types=1);

namespace Ronda\Api\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Ronda\Identity\Domain\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cada endpoint declara su alcance. RONDA-PLAN-MAESTRO.md sec. 13.1
 *
 * «Deny by default»: sin alcance declarado en la ruta no se entra, aunque el
 * token sea valido. Es la diferencia con el sistema anterior, donde una ruta
 * nueva nacia abierta por olvido. Hay un test que recorre la tabla de rutas y
 * falla si alguna de la API no declara el suyo.
 *
 * Esto NO sustituye a la Policy: el alcance dice que puede hacer el TOKEN; la
 * Policy, que puede hacer la PERSONA. Los dos, y en este orden, porque mirar
 * un alcance no toca la base.
 */
final readonly class EnsureTokenScope
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $usuario = $request->user();
        $token = $usuario instanceof User ? $usuario->currentAccessToken() : null;

        if ($token instanceof PersonalAccessToken && $token->can($scope)) {
            return $next($request);
        }

        return new JsonResponse([
            'message' => __('This token cannot do that.'),
            'code' => 'missing_scope',
            'required_scope' => $scope,
        ], 403);
    }
}
