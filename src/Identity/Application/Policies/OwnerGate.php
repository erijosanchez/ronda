<?php

declare(strict_types=1);

namespace Ronda\Identity\Application\Policies;

use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\RoleName;

/**
 * El propietario del tenant puede todo. RONDA-PLAN-MAESTRO.md sec. 10.3
 *
 * Se registra como `Gate::before`, y es el UNICO atajo de ese tipo en la
 * aplicacion. En particular no lo hay para el soporte de Ronda: entrar en la
 * cuenta de un cliente pasa por suplantacion registrada, no por un permiso
 * silencioso.
 *
 * Vive en Application\Policies porque es lo unico que puede consultar roles:
 * el test de arquitectura prohibe `hasRole()` en cualquier otro sitio.
 *
 * Devuelve null, no false, cuando el usuario no es propietario. `false`
 * cortaria la cadena y ninguna Policy llegaria a ejecutarse.
 */
final readonly class OwnerGate
{
    /**
     * `Gate::before` invoca esto con ($user, $ability, $arguments). Aqui solo
     * se declara $user porque el resto no se usa y PHP ignora los argumentos
     * sobrantes; declararlos sin usarlos hacia saltar a Rector.
     */
    public function __invoke(User $user): ?bool
    {
        return $user->hasRole(RoleName::Owner->value) ? true : null;
    }
}
