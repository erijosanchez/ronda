<?php

declare(strict_types=1);

namespace Ronda\Identity\Application\Policies;

use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\PermissionName;
use Ronda\Identity\Domain\RoleName;

/**
 * Quien puede ver y administrar a los usuarios del cliente.
 * RONDA-PLAN-MAESTRO.md sec. 10.3
 *
 * Comprueba PERMISOS, no roles: asi el cliente puede reorganizar sus roles sin
 * que haya que tocar codigo. La unica excepcion es proteger al propietario,
 * que si es una regla sobre el rol.
 *
 * El propietario ni aparece aqui: OwnerGate lo resuelve antes.
 */
final readonly class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermissionTo(PermissionName::UserView->value);
    }

    public function view(User $actor, User $target): bool
    {
        // Cualquiera puede ver su propia ficha sin permiso de nada.
        return $actor->is($target)
            || $actor->hasPermissionTo(PermissionName::UserView->value);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermissionTo(PermissionName::UserManage->value);
    }

    /**
     * Decidir en que sedes trabaja alguien es gestionar su acceso, no gestionar
     * el parque: pide `user.manage`, no `site.manage`.
     *
     * Quien no administra sedes solo vera en el selector las suyas, porque
     * AssignedSitesScope le filtra la consulta: no se puede dar acceso a una
     * sede que uno mismo no alcanza.
     *
     * Con los roles predefinidos ese limite no llega a notarse, porque los dos
     * que traen `user.manage` (owner y admin) traen tambien `site.manage`.
     * Empieza a valer en cuanto el cliente cree un rol que gestione personas
     * sin administrar sedes, que es algo que el plan contempla (sec. 10.3).
     */
    public function assignSites(User $actor, User $target): bool
    {
        return $actor->hasPermissionTo(PermissionName::UserManage->value);
    }

    public function update(User $actor, User $target): bool
    {
        if ($this->isOwner($target)) {
            // Solo el propietario se edita a si mismo. Sin esto, un admin puede
            // cambiarle el correo al dueno y quedarse con la cuenta.
            return $actor->is($target);
        }

        return $actor->is($target)
            || $actor->hasPermissionTo(PermissionName::UserManage->value);
    }

    /**
     * OJO: estas dos guardas NO alcanzan al propietario. `Gate::before`
     * (OwnerGate) devuelve true antes de que esta Policy llegue a ejecutarse,
     * asi que el propietario puede borrarse a si mismo.
     *
     * Ese invariante no es de autorizacion sino de negocio, y su sitio es la
     * Action que borre usuarios: «no se puede dejar al tenant sin propietario».
     * Cuando exista DeleteUser, va alli. Anotado en docs/HANDOFF.md.
     */
    public function delete(User $actor, User $target): bool
    {
        // Nadie se borra a si mismo: deja al cliente sin forma de entrar si es
        // el ultimo con permisos.
        if ($actor->is($target)) {
            return false;
        }

        // Al propietario no lo borra nadie. Cambiar de propietario es una
        // operacion aparte, con su propia auditoria.
        if ($this->isOwner($target)) {
            return false;
        }

        return $actor->hasPermissionTo(PermissionName::UserManage->value);
    }

    private function isOwner(User $user): bool
    {
        return $user->hasRole(RoleName::Owner->value);
    }
}
