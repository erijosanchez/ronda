<?php

declare(strict_types=1);

namespace Ronda\Platform\Presentation\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\Request;
use Ronda\Platform\Domain\Models\PlatformUser;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deja pasar solo al equipo de Ronda, y solo con 2FA.
 * RONDA-PLAN-MAESTRO.md sec. 15.4
 *
 * Comprueba las tres cosas, no una: que haya sesion del guard `platform`, que
 * la cuenta siga activa y que tenga el segundo factor confirmado. Las dos
 * ultimas se miran en CADA peticion y no solo al entrar: dar de baja a alguien
 * del equipo tiene que echarlo de la sesion que ya tenia abierta, no esperar a
 * que cierre el navegador.
 */
final readonly class EnsureBackOfficeAccess
{
    public function __construct(
        private Auth $auth,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $this->auth->guard('platform')->user();

        if (! $usuario instanceof PlatformUser || ! $usuario->canUseBackOffice()) {
            $this->auth->guard('platform')->logout();

            return to_route('back-office.login');
        }

        return $next($request);
    }
}
