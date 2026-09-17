<?php

declare(strict_types=1);

namespace Ronda\Workflow\Application\Actions;

use Ronda\Identity\Domain\Models\User;
use Ronda\Submissions\Domain\Models\Submission;
use Ronda\Workflow\Domain\Exceptions\CannotReview;
use Ronda\Workflow\Domain\Models\SubmissionComment;

/**
 * Escribe en la conversacion de un envio. RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * Quien puede ver el envio puede comentarlo (Policy `comment`). Un comentario
 * no cambia el estado: para eso estan aprobar y rechazar.
 */
final readonly class CommentOnSubmission
{
    public const int MAX_LENGTH = 2000;

    /**
     * @throws CannotReview
     */
    public function __invoke(Submission $submission, User $author, string $body): SubmissionComment
    {
        $body = trim($body);

        if ($body === '') {
            throw CannotReview::emptyComment();
        }

        return SubmissionComment::create([
            'submission_id' => $submission->getKey(),
            'author_id' => $author->getKey(),
            'body' => mb_substr($body, 0, self::MAX_LENGTH),
        ]);
    }
}
