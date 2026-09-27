<?php

declare(strict_types=1);

namespace Ronda\Api\Presentation\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Ronda\Identity\Domain\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cuota por token, con cabeceras. RONDA-PLAN-MAESTRO.md sec. 13.1
 *
 * Se cuenta por TOKEN y no por IP: varias integraciones del mismo cliente
 * salen de la misma IP —y a veces de la misma nube que otros clientes—, asi
 * que una cuota por IP castigaria a quien no tiene la culpa.
 *
 * Siempre se devuelven las cabeceras `X-RateLimit-*`, no solo al cortar:
 * quien integra necesita saber cuanto le queda ANTES de quedarse sin margen,
 * que es lo que permite espaciar las llamadas en vez de reintentarlas.
 */
final readonly class ApiRateLimit
{
    public function __construct(
        private RateLimiter $limiter,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $porMinuto = (int) config('api.rate_limit_per_minute', 120);
        $llave = $this->key($request);

        if ($this->limiter->tooManyAttempts($llave, $porMinuto)) {
            $espera = $this->limiter->availableIn($llave);

            return new JsonResponse([
                'message' => __('Too many requests. Wait :seconds seconds.', ['seconds' => $espera]),
                'code' => 'rate_limited',
            ], 429, $this->headers($porMinuto, 0, $espera));
        }

        $this->limiter->hit($llave, 60);

        $respuesta = $next($request);

        // `headers->add()` y no `withHeaders()`: lo segundo solo existe en la
        // respuesta de Laravel, y por aqui pasa cualquier respuesta de
        // Symfony —incluida la de un archivo servido en streaming—.
        foreach ($this->headers($porMinuto, $this->limiter->remaining($llave, $porMinuto)) as $nombre => $valor) {
            $respuesta->headers->set($nombre, (string) $valor);
        }

        return $respuesta;
    }

    /**
     * @return array<string, string|int>
     */
    private function headers(int $limite, int $restantes, ?int $espera = null): array
    {
        $cabeceras = [
            'X-RateLimit-Limit' => $limite,
            'X-RateLimit-Remaining' => max($restantes, 0),
        ];

        if ($espera !== null) {
            $cabeceras['Retry-After'] = $espera;
        }

        return $cabeceras;
    }

    /**
     * La llave del contador: el token si lo hay, la IP si todavia no se sabe
     * quien llama (una peticion sin autenticar tambien consume servidor).
     */
    private function key(Request $request): string
    {
        $usuario = $request->user();

        // `currentAccessToken()` lo aporta el trait de Sanctum, que no esta en
        // el contrato de usuario: se comprueba antes de pedirlo en vez de dar
        // por hecho que quien llega tiene token.
        $token = $usuario instanceof User ? $usuario->currentAccessToken() : null;

        return $token instanceof PersonalAccessToken
            ? 'api-token:'.$token->getKey()
            : 'api-ip:'.$request->ip();
    }
}
