<?php

declare(strict_types=1);

namespace Ronda\Platform\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ata la sesion al tenant en el que se abrio. RONDA-PLAN-MAESTRO.md sec. 7.5
 *
 * Sin esto, una sesion abierta en el cliente A sirve tal cual en el dominio del
 * cliente B. El motivo es que la sesion solo guarda el identificador numerico
 * del usuario, y los identificadores empiezan en 1 en cada base: al presentar
 * esa sesion en B, el guard resuelve el usuario 1 de B y entra como el.
 *
 * Comprobado antes del arreglo: con sesion de `alfa`, `GET beta/sedes`
 * respondia 200 con los datos de beta, y `/user/two-factor-recovery-codes`
 * devolvia los codigos de recuperacion del usuario de beta.
 *
 * En un navegador no ocurre, porque SESSION_DOMAIN esta vacio y la cookie es
 * host-only: no viaja de un subdominio a otro. Esta es la segunda capa, para
 * cuando alguien traslada la cookie a mano. Por eso mismo no hay conflicto
 * legitimo que resolver: cada dominio tiene su propia cookie y su propia
 * sesion, asi que si los tenants no coinciden es que algo va mal.
 */
final class EnsureSessionBelongsToTenant
{
    /**
     * Clave donde se anota a que cliente pertenece esta sesion.
     */
    private const string KEY = 'tenant_id';

    public function handle(Request $request, Closure $next): Response
    {
        $current = tenant('id');

        // Fuera del contexto de un tenant no hay nada que atar.
        if ($current === null) {
            return $next($request);
        }

        $session = $request->session();
        $owner = $session->get(self::KEY);

        if ($owner === null) {
            $session->put(self::KEY, $current);

            return $next($request);
        }

        if ($owner !== $current) {
            // Se destruye entera, no solo se deniega: una sesion presentada en
            // el dominio equivocado no vuelve a ser de fiar en ninguno.
            Auth::guard()->logout();
            $session->invalidate();
            $session->regenerateToken();
            $session->put(self::KEY, $current);

            // Y se corta aqui mismo, sin dejar seguir la cadena.
            //
            // Delegar en el middleware `auth` de mas abajo no sirve: Laravel
            // ordena los middleware por prioridad y `Authenticate` corre ANTES
            // que este, asi que la primera peticion ya habia pasado el control
            // y se servia entera. Se veia en el recorrido: la primera ruta del
            // barrido devolvia 200 y solo las siguientes quedaban protegidas.
            //
            // Cortar aqui hace el resultado independiente del orden.
            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
