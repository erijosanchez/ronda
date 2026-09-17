<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Application\Policies;

use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\PermissionName;
use Ronda\Scheduling\Domain\Models\Schedule;

/**
 * Quien puede ver y administrar programaciones. RONDA-PLAN-MAESTRO.md
 * sec. 10.3
 *
 * Administrar una programacion es decidir que se le exige a cada sede y cuando:
 * crear, cambiar, pausar y reanudar piden el mismo permiso porque todas mueven
 * las obligaciones de manana.
 */
final readonly class SchedulePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermissionTo(PermissionName::ScheduleView->value);
    }

    public function view(User $actor, Schedule $schedule): bool
    {
        return $actor->hasPermissionTo(PermissionName::ScheduleView->value);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermissionTo(PermissionName::ScheduleManage->value);
    }

    public function update(User $actor, Schedule $schedule): bool
    {
        return $actor->hasPermissionTo(PermissionName::ScheduleManage->value);
    }
}
