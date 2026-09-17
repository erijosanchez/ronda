<?php

declare(strict_types=1);

namespace Ronda\Evidence\Application\Policies;

use Ronda\Evidence\Domain\Models\Attachment;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\PermissionName;
use Ronda\Submissions\Domain\Models\Submission;

/**
 * Quien puede ver un archivo de evidencia. ADR 0009.
 *
 * Se evalua dos veces por acceso: antes de firmar la URL y otra vez al
 * servirla. Una URL firmada dura cinco minutos, pero si en ese tiempo a alguien
 * le quitan la sede, deja de servir.
 *
 * Exige `evidence.view` y ADEMAS poder ver el envio al que pertenece: el
 * permiso dice que clase de cosa puede mirar; el envio, cual.
 */
final readonly class AttachmentPolicy
{
    public function view(User $actor, Attachment $attachment): bool
    {
        if (! $actor->hasPermissionTo(PermissionName::EvidenceView->value)
            && $attachment->uploaded_by !== $actor->getKey()) {
            return false;
        }

        $submission = Submission::query()->find($attachment->submission_id);

        return $submission instanceof Submission && $actor->can('view', $submission);
    }
}
