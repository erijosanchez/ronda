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
 *
 * Revisar, aprobar y corregir exigen ADEMAS poder verlo: un supervisor de la
 * zona norte no decide sobre un arqueo de la zona sur. Las reglas del flujo
 * (no revisar lo propio, no decidir lo que tomo otro) no son permisos y viven
 * en las Actions de Workflow.
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

    /**
     * Entregar o corregir en alguna sede. La sede concreta la decide `correct`.
     */
    public function create(User $actor): bool
    {
        return $actor->hasPermissionTo(PermissionName::SubmissionCreate->value);
    }

    /**
     * Ver la bandeja de revision.
     */
    public function viewInbox(User $actor): bool
    {
        return $actor->hasPermissionTo(PermissionName::SubmissionReview->value)
            || $actor->hasPermissionTo(PermissionName::SubmissionApprove->value);
    }

    /**
     * Tomar y rechazar.
     */
    public function review(User $actor, Submission $submission): bool
    {
        return $actor->hasPermissionTo(PermissionName::SubmissionReview->value)
            && $this->view($actor, $submission);
    }

    /**
     * Aprobar es un permiso aparte: se puede confiar a alguien la revision de
     * un arqueo sin confiarle darlo por bueno.
     */
    public function approve(User $actor, Submission $submission): bool
    {
        return $actor->hasPermissionTo(PermissionName::SubmissionApprove->value)
            && $this->view($actor, $submission);
    }

    /**
     * Corregir un envio rechazado: quien puede entregar en esa sede.
     */
    public function correct(User $actor, Submission $submission): bool
    {
        return $actor->hasPermissionTo(PermissionName::SubmissionCreate->value)
            && $this->view($actor, $submission);
    }

    public function comment(User $actor, Submission $submission): bool
    {
        return $this->view($actor, $submission);
    }
}
