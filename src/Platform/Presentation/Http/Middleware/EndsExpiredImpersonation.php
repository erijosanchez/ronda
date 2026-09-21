<?php

declare(strict_types=1);

namespace Ronda\Platform\Presentation\Http\Middleware;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\Request;
use Ronda\Platform\Application\Actions\EndImpersonation;
use Ronda\Platform\Domain\Models\ImpersonationEntry;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra sola la sesion de soporte cuando se le acaba el tiempo.
 * RONDA-PLAN-MAESTRO.md sec. 15.4
 *
 * El limite de tiempo del plan seria papel mojado si dependiera de que alguien
 * pulse «salir»: el caso que hay que cubrir es el de quien cierra el portatil y
 * se va a almorzar con la sesion de un cliente abierta.
 *
 * Se comprueba en cada peticion y contra la BASE, no contra la sesion: la
 * sesion la controla el navegador, y el registro es lo que se audita.
 */
final readonly class EndsExpiredImpersonation
{
    public function __construct(
        private Auth $auth,
        private EndImpersonation $end,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $datos = $request->session()->get('impersonation');

        if (! is_array($datos) || ! isset($datos['entry'])) {
            return $next($request);
        }

        $entrada = ImpersonationEntry::query()->find($datos['entry']);

        if ($entrada instanceof ImpersonationEntry && $entrada->isOpen(CarbonImmutable::now('UTC'))) {
            return $next($request);
        }

        // Caducada o ya cerrada desde el back-office: fuera.
        if ($entrada instanceof ImpersonationEntry) {
            ($this->end)($entrada);
        }

        $this->auth->guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login')
            ->with('status', __('The support session has ended.'));
    }
}
