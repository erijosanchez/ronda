<?php

declare(strict_types=1);

namespace Ronda\Workflow\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Ronda\Evidence\Application\Actions\DiscardEvidence;
use Ronda\Evidence\Application\Data\EvidenceUpload;
use Ronda\Evidence\Domain\EvidenceKind;
use Ronda\Forms\Domain\Models\TemplateVersion;
use Ronda\Identity\Domain\Models\User;
use Ronda\Submissions\Application\Actions\StoreAnswerEvidence;
use Ronda\Submissions\Domain\Exceptions\InvalidAnswers;
use Ronda\Submissions\Domain\Models\Submission;
use Ronda\Submissions\Domain\Services\AnswerValidator;
use Ronda\Submissions\Domain\Services\ReportableValues;
use Ronda\Submissions\Domain\States\Rejected;
use Ronda\Submissions\Domain\States\Submitted;
use Ronda\Workflow\Domain\Events\SubmissionCorrected;
use Ronda\Workflow\Domain\Exceptions\CannotReview;
use Ronda\Workflow\Domain\Models\SubmissionRevision;
use Ronda\Workflow\Domain\Models\SubmissionTransition;
use Throwable;

/**
 * Corrige un envio rechazado y lo devuelve a la bandeja.
 * RONDA-PLAN-MAESTRO.md sec. 9.4
 *
 *   Rechazado --corregir--> Enviado
 *
 * En una transaccion, en este orden:
 *
 *   1. Se valida contra la version del formulario CON LA QUE se respondio, no
 *      contra la vigente: una correccion arregla aquel envio, no lo convierte
 *      en otro.
 *   2. Lo que decia antes se guarda en `submission_revisions`, con el motivo
 *      del rechazo. Lo rechazado no desaparece.
 *   3. Evidencia: los campos con archivos nuevos los sustituyen; los que no,
 *      conservan los suyos. Los archivos sustituidos siguen en `attachments`
 *      (los referencia la revision guardada).
 *   4. `data` y la replica reportable se reescriben juntas (ADR 0012).
 *   5. Vuelve a `submitted`, sin revisor.
 *
 * `submitted_at` y el retraso no cambian: miden la entrega original. La
 * correccion queda con su hora en el historial.
 *
 * La Policy (`correct`) decide quien corrige; aqui, que el envio se pueda
 * corregir.
 */
final readonly class CorrectSubmission
{
    public function __construct(
        private ConnectionInterface $connection,
        private AnswerValidator $validator,
        private ReportableValues $reportable,
        private StoreAnswerEvidence $storeAnswerEvidence,
        private DiscardEvidence $discardEvidence,
        private RecordTransition $recordTransition,
        private Dispatcher $events,
    ) {}

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, list<EvidenceUpload>>  $evidence  archivos NUEVOS por campo
     *
     * @throws CannotReview
     * @throws InvalidAnswers
     */
    public function __invoke(Submission $submission, User $author, array $answers, array $evidence = []): Submission
    {
        /** @var list<string> $escritas */
        $escritas = [];

        try {
            return $this->connection->transaction(
                function () use ($submission, $author, $answers, $evidence, &$escritas): Submission {
                    return $this->correct($submission, $author, $answers, $evidence, $escritas);
                },
            );
        } catch (Throwable $e) {
            ($this->discardEvidence)($escritas);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, list<EvidenceUpload>>  $evidence
     * @param  list<string>  $escritas
     */
    private function correct(Submission $submission, User $author, array $answers, array $evidence, array &$escritas): Submission
    {
        /** @var Submission $submission */
        $submission = Submission::query()->lockForUpdate()->findOrFail($submission->getKey());

        if (! $submission->state instanceof Rejected) {
            throw CannotReview::notCorrectable($submission->state->getValue());
        }

        /** @var TemplateVersion $version */
        $version = TemplateVersion::query()->findOrFail($submission->template_version_id);
        $schema = $version->formSchema();

        // Evidencia que ya habia, por campo. Cuenta para lo obligatorio salvo
        // que llegue algo nuevo que la sustituya.
        $previa = [];
        $conteo = [];

        foreach ($schema->fields as $field) {
            if (! EvidenceKind::forFieldType($field->type) instanceof EvidenceKind) {
                continue;
            }

            $previa[$field->key] = $submission->evidenceIds($field->key);
            $nuevos = count($evidence[$field->key] ?? []);
            $conteo[$field->key] = $nuevos > 0 ? $nuevos : count($previa[$field->key]);
        }

        $limpias = $this->validator->validate($schema, $answers, $conteo);

        SubmissionRevision::create([
            'submission_id' => $submission->getKey(),
            'number' => $submission->revision,
            'template_version_id' => $submission->template_version_id,
            'data' => $submission->data,
            'rejection_comment' => $this->lastRejectionComment($submission),
        ]);

        $limpias = ($this->storeAnswerEvidence)($submission, $schema, $limpias, $evidence, $author, $escritas, $previa);

        $submission->values()->delete();
        $filas = $this->reportable->extract($schema, $limpias);

        if ($filas !== []) {
            $submission->values()->createMany($filas);
        }

        $submission->forceFill([
            'data' => $limpias,
            'revision' => $submission->revision + 1,
            'reviewer_id' => null,
            'review_started_at' => null,
            'reviewed_at' => null,
        ]);
        $submission->state->transitionTo(Submitted::class);

        ($this->recordTransition)($submission, Rejected::$name, Submitted::$name, $author);

        $this->events->dispatch(new SubmissionCorrected($submission->id, (int) $author->getKey()));

        return $submission;
    }

    private function lastRejectionComment(Submission $submission): ?string
    {
        $comentario = SubmissionTransition::query()
            ->where('submission_id', $submission->getKey())
            ->where('to_state', Rejected::$name)
            ->latest('id')
            ->value('comment');

        return is_string($comentario) ? $comentario : null;
    }
}
