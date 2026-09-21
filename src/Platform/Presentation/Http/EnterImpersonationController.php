<?php

declare(strict_types=1);

namespace Ronda\Platform\Presentation\Http;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Ronda\Platform\Domain\Models\ImpersonationEntry;
use Stancl\Tenancy\Database\Models\ImpersonationToken;
use Stancl\Tenancy\Features\UserImpersonation;

/**
 * La puerta por la que soporte entra a la cuenta de un cliente.
 * RONDA-PLAN-MAESTRO.md sec. 15.4
 *
 * Vive en el dominio del CLIENTE y es publica porque el vale ES la credencial:
 * exigir sesion aqui seria pedirle al visitante la sesion que viene a abrir. El
 * vale es de un solo uso, caduca en un minuto y lo emitio el back-office
 * despues de anotar el motivo y avisar al cliente.
 *
 * Lo que anade este controlador a lo que hace el paquete es la memoria de la
 * sesion: quien entro, por que y hasta cuando. Sin eso, la sesion suplantada
 * seria indistinguible de la del propio usuario, y no habria ni banner ni
 * limite de tiempo.
 */
final class EnterImpersonationController extends Controller
{
    /** Lo que dura el vale. Un minuto alcanza para redirigir; un dia, no. */
    private const int TOKEN_SECONDS = 60;

    public function __invoke(string $token): RedirectResponse
    {
        // Se lee ANTES: `makeResponse` borra el vale al usarlo.
        $vale = ImpersonationToken::query()->find($token);

        $entrada = $vale === null ? null : ImpersonationEntry::query()
            ->where('tenant_id', $vale->tenant_id)
            ->where('impersonated_user_id', $vale->user_id)
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();

        // Sin registro no se entra, aunque el vale sea valido: una sesion
        // suplantada que no esta anotada es exactamente lo que el plan
        // prohibe.
        if (! $entrada instanceof ImpersonationEntry || ! $entrada->isOpen()) {
            abort(403);
        }

        $respuesta = UserImpersonation::makeResponse($token, self::TOKEN_SECONDS);

        session()->put('impersonation', [
            'entry' => $entrada->getKey(),
            'expires_at' => $entrada->expires_at->toIso8601String(),
            'reason' => $entrada->reason,
        ]);

        return $respuesta;
    }
}
