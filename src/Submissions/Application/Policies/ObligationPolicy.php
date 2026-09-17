<?php

declare(strict_types=1);

namespace Ronda\Submissions\Application\Policies;

use Ronda\Directory\Domain\Models\Site;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\PermissionName;
use Ronda\Scheduling\Domain\Models\Obligation;

/**
 * Quien puede ver y cumplir obligaciones. RONDA-PLAN-MAESTRO.md sec. 10.3
 *
 * Entregar exige DOS cosas: el permiso `submission.create` y alcanzar la sede.
 * Un encargado puede enviar reportes, pero solo de las sedes que tiene
 * asignadas; sin la segunda comprobacion, podria cumplir la obligacion de otro
 * local cambiando un id en la URL.
 *
 * La sede se comprueba reutilizando SitePolicy y no repitiendo aqui la logica de
 * asignacion: la frontera por sede tiene un solo sitio donde decidirse.
 */
final readonly class ObligationPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermissionTo(PermissionName::SubmissionView->value)
            || $actor->hasPermissionTo(PermissionName::SubmissionCreate->value);
    }

    public function submit(User $actor, Obligation $obligation): bool
    {
        if (! $actor->hasPermissionTo(PermissionName::SubmissionCreate->value)) {
            return false;
        }

        // Sin el scope: se busca la sede real y es SitePolicy quien decide si
        // este usuario la alcanza.
        $site = Site::query()->withoutGlobalScopes()->find($obligation->site_id);

        return $site instanceof Site && $actor->can('view', $site);
    }
}
