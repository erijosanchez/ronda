<?php

declare(strict_types=1);

namespace Ronda\Workflow\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Ronda\Identity\Domain\Models\User;
use Ronda\Submissions\Domain\Models\Submission;
use Ronda\Submissions\Domain\States\Rejected;
use Ronda\Submissions\Domain\States\UnderReview;
use Ronda\Workflow\Domain\Events\SubmissionRejected;
use Ronda\Workflow\Domain\Exceptions\CannotReview;

/**
 * Rechaza un envio para que su sede lo corrija. RONDA-PLAN-MAESTRO.md sec. 9.4
 *
 *   En revision --rechazar--> Rechazado
 *
 * Exige comentario: un rechazo sin motivo le deja a la sede adivinando que
 * corregir. Se comprueba ANTES de tomar el envio, para que un rechazo sin
 * comentario no deje el envio reservado.
 *
 * La obligacion sigue cumplida: se entrego a tiempo o no, y eso no cambia
 * porque el contenido necesite correccion. La calidad se mide aparte.
 */
final readonly class RejectSubmission
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
    public function __invoke(Submission $submission, User $reviewer, string $comment, ?CarbonImmutable $now = null): Submission
    {
        if (trim($comment) === '') {
            throw CannotReview::commentRequired();
        }

        $now ??= CarbonImmutable::now('UTC');

        return $this->connection->transaction(function () use ($submission, $reviewer, $comment, $now): Submission {
            $submission = ($this->take)($submission, $reviewer, $now);

            $submission->forceFill(['reviewed_at' => $now]);
            $submission->state->transitionTo(Rejected::class);

            ($this->recordTransition)($submission, UnderReview::$name, Rejected::$name, $reviewer, $comment);

            $this->events->dispatch(new SubmissionRejected($submission->id, (int) $reviewer->getKey()));

            return $submission;
        });
    }
}
