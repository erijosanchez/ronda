<?php

declare(strict_types=1);

namespace Ronda\Directory\Application\Policies;

use Ronda\Directory\Domain\Models\Position;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\PermissionName;

/**
 * Quien puede ver y administrar cargos. RONDA-PLAN-MAESTRO.md sec. 10.3
 *
 * Un cargo describe el organigrama del cliente; no concede permisos (eso son
 * los roles). Se administra con `user.manage`, que es quien organiza a las
 * personas, y se ve con `user.view`.
 */
final readonly class PositionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermissionTo(PermissionName::UserView->value);
    }

    public function view(User $actor, Position $position): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermissionTo(PermissionName::UserManage->value);
    }

    public function update(User $actor, Position $position): bool
    {
        return $this->create($actor);
    }

    public function delete(User $actor, Position $position): bool
    {
        return $this->create($actor);
    }
}
