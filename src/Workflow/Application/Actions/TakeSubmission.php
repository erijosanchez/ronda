<?php

declare(strict_types=1);

namespace Ronda\Workflow\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Ronda\Identity\Domain\Models\User;
use Ronda\Submissions\Domain\Models\Submission;
use Ronda\Submissions\Domain\States\Submitted;
use Ronda\Submissions\Domain\States\UnderReview;
use Ronda\Workflow\Domain\Events\SubmissionTaken;
use Ronda\Workflow\Domain\Exceptions\CannotReview;

/**
 * Toma un envio de la bandeja para revisarlo. RONDA-PLAN-MAESTRO.md sec. 9.4
 *
 *   Enviado --tomar--> En revision
 *
 * Tomar reserva la decision: mientras este en revision, solo quien lo tomo
 * aprueba o rechaza. Sin esto, dos supervisores abriendo la misma bandeja
 * podrian aprobar y rechazar el mismo arqueo.
 *
 * Es idempotente para quien ya lo tiene: volver a tomarlo no hace nada. Asi
 * aprobar y rechazar pueden tomarlo de paso sin un clic mas.
 *
 * La Policy (`review`) decide si esta persona revisa envios de esa sede; aqui
 * se deciden las reglas del flujo.
 */
final readonly class TakeSubmission
{
    public function __construct(
        private ConnectionInterface $connection,
        private RecordTransition $recordTransition,
        private Dispatcher $events,
    ) {}

    /**
     * @return Submission el envio releido, bloqueado y en revision por `$reviewer`
     *
     * @throws CannotReview
     */
    public function __invoke(Submission $submission, User $reviewer, ?CarbonImmutable $now = null): Submission
    {
        $now ??= CarbonImmutable::now('UTC');

        return $this->connection->transaction(function () use ($submission, $reviewer, $now): Submission {
            // Con bloqueo: dos supervisores tomando a la vez. El segundo espera
            // y lo encuentra ya en revision por el primero.
            /** @var Submission $submission */
            $submission = Submission::query()->lockForUpdate()->findOrFail($submission->getKey());

            if ($submission->author_id === $reviewer->getKey()) {
                throw CannotReview::ownSubmission();
            }

            if ($submission->state instanceof UnderReview) {
                if ($submission->reviewer_id === $reviewer->getKey()) {
                    return $submission;
                }

                throw CannotReview::takenByOther((string) $submission->reviewer?->name);
            }

            if (! $submission->state instanceof Submitted) {
                throw CannotReview::notReviewable($submission->state->getValue());
            }

            $submission->forceFill([
                'reviewer_id' => $reviewer->getKey(),
                'review_started_at' => $now,
            ]);
            $submission->state->transitionTo(UnderReview::class);

            ($this->recordTransition)($submission, Submitted::$name, UnderReview::$name, $reviewer);

            $this->events->dispatch(new SubmissionTaken($submission->id, (int) $reviewer->getKey()));

            return $submission;
        });
    }
}
