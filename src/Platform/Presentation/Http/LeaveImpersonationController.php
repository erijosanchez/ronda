<?php

declare(strict_types=1);

namespace Ronda\Platform\Presentation\Http;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ronda\Platform\Application\Actions\EndImpersonation;
use Ronda\Platform\Domain\Models\ImpersonationEntry;

/**
 * El boton de salir del banner. RONDA-PLAN-MAESTRO.md sec. 15.4
 *
 * Cierra la sesion suplantada y la anota como terminada. No devuelve al
 * back-office porque esto ocurre en el dominio del cliente y aquella sesion
 * vive en el central: quien da soporte vuelve a su pestana, que sigue abierta.
 */
final class LeaveImpersonationController extends Controller
{
    public function __invoke(Request $request, Auth $auth, EndImpersonation $end): RedirectResponse
    {
        $datos = $request->session()->get('impersonation');

        if (is_array($datos) && isset($datos['entry'])) {
            $entrada = ImpersonationEntry::query()->find($datos['entry']);

            if ($entrada instanceof ImpersonationEntry) {
                $end($entrada);
            }
        }

        $auth->guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login')
            ->with('status', __('The support session has ended.'));
    }
}
