<?php

declare(strict_types=1);

namespace Ronda\Platform\Presentation\Http;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Cierra la sesion del back-office. RONDA-PLAN-MAESTRO.md sec. 15.4
 *
 * Invalida la sesion y rota el token: cerrar sesion tiene que dejar inservible
 * lo que habia, no solo olvidarse del usuario.
 *
 * Solo toca el guard `platform`: si alguien del equipo tuviera ademas sesion
 * abierta como usuario de un cliente, no es asunto de este boton.
 */
final class BackOfficeLogoutController extends Controller
{
    public function __invoke(Request $request, Auth $auth): RedirectResponse
    {
        $auth->guard('platform')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('back-office.login');
    }
}
