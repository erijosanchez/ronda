<?php

declare(strict_types=1);

namespace Ronda\Workflow\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Ronda\Identity\Domain\Models\User;
use Ronda\Submissions\Domain\Models\Submission;
use Ronda\Submissions\Domain\States\Approved;
use Ronda\Submissions\Domain\States\UnderReview;
use Ronda\Workflow\Domain\Events\SubmissionApproved;
use Ronda\Workflow\Domain\Exceptions\CannotReview;

/**
 * Aprueba un envio. RONDA-PLAN-MAESTRO.md sec. 9.4
 *
 *   En revision --aprobar--> Aprobado (final)
 *
 * Si todavia estaba en la bandeja, lo toma de paso (TakeSubmission): el
 * historial registra las dos transiciones y el flujo no se salta ningun paso.
 *
 * El comentario es opcional al aprobar.
 */
final readonly class ApproveSubmission
{
    public function __construct(
        private ConnectionInterface $connection,
        private TakeSubmission $take,
        private RecordTransition $recordTransition,
        private Dispatcher $events,
    ) {}

    /**
     * @throws CannotReview
     */
    public function __invoke(Submission $submission, User $reviewer, ?string $comment = null, ?CarbonImmutable $now = null): Submission
    {
        $now ??= CarbonImmutable::now('UTC');

        return $this->connection->transaction(function () use ($submission, $reviewer, $comment, $now): Submission {
            $submission = ($this->take)($submission, $reviewer, $now);

            $submission->forceFill(['reviewed_at' => $now]);
            $submission->state->transitionTo(Approved::class);

            ($this->recordTransition)($submission, UnderReview::$name, Approved::$name, $reviewer, $comment);

            $this->events->dispatch(new SubmissionApproved($submission->id, (int) $reviewer->getKey()));

            return $submission;
        });
    }
}
