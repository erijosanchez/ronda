<?php

declare(strict_types=1);

namespace Ronda\Workflow\Application\Actions;

use Ronda\Identity\Domain\Models\User;
use Ronda\Submissions\Domain\Models\Submission;
use Ronda\Workflow\Domain\Models\SubmissionTransition;

/**
 * Anota un cambio de estado en el historial del envio.
 *
 * No cambia el estado: lo anota quien lo cambia, dentro de su misma
 * transaccion. Un historial escrito aparte podria contar una transicion que no
 * llego a guardarse.
 */
final readonly class RecordTransition
{
    public function __invoke(
        Submission $submission,
        ?string $from,
        string $to,
        User $actor,
        ?string $comment = null,
    ): SubmissionTransition {
        return SubmissionTransition::create([
            'submission_id' => $submission->getKey(),
            'from_state' => $from,
            'to_state' => $to,
            'actor_id' => $actor->getKey(),
            'comment' => $comment === null || trim($comment) === '' ? null : trim($comment),
        ]);
    }
}
