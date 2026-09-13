<?php

declare(strict_types=1);

namespace Ronda\Directory\Application\Policies;

use Ronda\Directory\Domain\Models\Site;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\PermissionName;

/**
 * Quien puede ver y administrar sedes. RONDA-PLAN-MAESTRO.md sec. 10.3
 *
 * Segunda capa de la frontera por sede: AssignedSitesScope ya filtra la
 * consulta, pero un `withoutGlobalScopes()` puesto para otra cosa la
 * desactiva. Aqui se vuelve a comprobar sobre el registro concreto.
 *
 * `viewAll` no es una habilidad de pantalla: la consulta AssignedSitesScope
 * para decidir si a ese usuario le filtra o no. Tenerla aqui es lo que
 * mantiene la autorizacion fuera del scope.
 */
final readonly class SitePolicy
{
    /**
     * Ve el parque entero, sin filtrar por asignacion.
     *
     * Quien administra sedes tiene que poder verlas todas, incluidas las que
     * todavia no ha asignado a nadie. El propietario ni llega aqui: OwnerGate
     * lo resuelve antes.
     */
    public function viewAll(User $actor): bool
    {
        return $actor->hasPermissionTo(PermissionName::SiteManage->value);
    }

    public function viewAny(User $actor): bool
    {
        return $actor->hasPermissionTo(PermissionName::SiteView->value);
    }

    public function view(User $actor, Site $site): bool
    {
        if (! $actor->hasPermissionTo(PermissionName::SiteView->value)) {
            return false;
        }

        return $this->viewAll($actor) || $this->isAssignedTo($actor, $site);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermissionTo(PermissionName::SiteManage->value);
    }

    public function update(User $actor, Site $site): bool
    {
        return $actor->hasPermissionTo(PermissionName::SiteManage->value);
    }

    public function delete(User $actor, Site $site): bool
    {
        return $actor->hasPermissionTo(PermissionName::SiteManage->value);
    }

    /**
     * Se consulta la tabla directamente y no la relacion del modelo: cargar
     * `users` de la sede para comprobar una pertenencia trae todo el personal
     * a memoria en cada comprobacion.
     */
    private function isAssignedTo(User $actor, Site $site): bool
    {
        return $site->users()
            ->whereKey($actor->getKey())
            ->exists();
    }
}
