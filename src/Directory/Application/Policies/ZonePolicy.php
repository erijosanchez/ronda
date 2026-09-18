<?php

declare(strict_types=1);

namespace Ronda\Directory\Application\Policies;

use Ronda\Directory\Domain\Models\Zone;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\PermissionName;

/**
 * Quien puede ver y administrar zonas. RONDA-PLAN-MAESTRO.md sec. 10.3
 *
 * Las zonas son la estructura donde cuelgan las sedes, asi que se gobiernan con
 * los mismos permisos: verlas con `site.view`, cambiarlas con `site.manage`. Un
 * permiso aparte solo para agrupar sedes seria mas configuracion sin decision
 * detras.
 *
 * No hay frontera por zona: una zona no contiene datos de operacion, solo
 * agrupa. Lo que se filtra por asignacion son las SEDES.
 */
final readonly class ZonePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermissionTo(PermissionName::SiteView->value);
    }

    public function view(User $actor, Zone $zone): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermissionTo(PermissionName::SiteManage->value);
    }

    public function update(User $actor, Zone $zone): bool
    {
        return $this->create($actor);
    }

    public function delete(User $actor, Zone $zone): bool
    {
        return $this->create($actor);
    }
}
