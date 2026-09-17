<?php

declare(strict_types=1);

namespace Ronda\Submissions\Application\Policies;

use Ronda\Directory\Domain\Models\Site;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\PermissionName;
use Ronda\Submissions\Domain\Models\Submission;

/**
 * Quien puede ver un envio. RONDA-PLAN-MAESTRO.md sec. 10.3
 *
 * Dos condiciones, como en la entrega: poder ver envios (o ser quien lo
 * entrego) y alcanzar la sede. Sin la segunda, cambiar el id en la URL
 * ensenaria el arqueo, y las fotos, de otro local.
 *
 * La evidencia hereda esta decision (AttachmentPolicy): ver un archivo es ver
 * una parte del envio.
 */
final readonly class SubmissionPolicy
{
    public function view(User $actor, Submission $submission): bool
    {
        $puedeVer = $actor->hasPermissionTo(PermissionName::SubmissionView->value)
            || $submission->author_id === $actor->getKey();

        if (! $puedeVer) {
            return false;
        }

        // Sin el scope: se busca la sede real y decide SitePolicy.
        $site = Site::query()->withoutGlobalScopes()->find($submission->site_id);

        return $site instanceof Site && $actor->can('view', $site);
    }
}
