<?php

declare(strict_types=1);

namespace Ronda\Workflow\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Ronda\Evidence\Domain\EvidenceKind;
use Ronda\Forms\Domain\Models\TemplateVersion;
use Ronda\Forms\Domain\ValueObjects\Field;
use Ronda\Forms\Domain\ValueObjects\FieldType;
use Ronda\Forms\Domain\ValueObjects\FormSchema;
use Ronda\Forms\Domain\ValueObjects\VisibilityCondition;
use Ronda\Identity\Domain\Models\User;
use Ronda\Submissions\Domain\Exceptions\InvalidAnswers;
use Ronda\Submissions\Domain\Models\Submission;
use Ronda\Submissions\Domain\States\Rejected;
use Ronda\Submissions\Presentation\Livewire\Concerns\CollectsEvidence;
use Ronda\Workflow\Application\Actions\CorrectSubmission;
use Ronda\Workflow\Domain\Exceptions\CannotReview;
use Ronda\Workflow\Domain\Models\SubmissionTransition;

/**
 * Corregir un envio rechazado. RONDA-PLAN-MAESTRO.md sec. 9.4
 *
 * El mismo formulario que la entrega (parcial `submissions::partials.fields`),
 * con la respuesta rechazada ya puesta y el motivo del rechazo arriba. Se
 * responde contra la version con la que se entrego, no contra la vigente.
 *
 * Valida, invoca la Action y devuelve (regla 1).
 */
final class SubmissionCorrectionForm extends Component
{
    use CollectsEvidence;

    public Submission $submission;

    /**
     * @var array<string, mixed>
     */
    public array $answers = [];

    public function mount(Submission $submission): void
    {
        $this->authorize('correct', $submission);

        $this->submission = $submission;
        $this->answers = $this->formStateFrom($submission);
    }

    public function submit(): void
    {
        $this->authorize('correct', $this->submission);

        /** @var User $author */
        $author = auth()->user();

        try {
            resolve(CorrectSubmission::class)($this->submission, $author, $this->answers, $this->collectedEvidence());
        } catch (InvalidAnswers $e) {
            foreach ($e->errors as $key => $message) {
                $this->addError("answers.{$key}", $message);
            }

            return;
        } catch (CannotReview $e) {
            $this->addError('submission', $e->getMessage());

            return;
        }

        // El borrador del dispositivo ya no hace falta.
        $this->dispatch('submission-saved');

        session()->flash('status', __('Correction submitted. It is back in review.'));
        $this->redirectRoute('submissions.show', ['submission' => $this->submission], navigate: true);
    }

    public function render(): View
    {
        $schema = $this->schema();
        $existentes = [];

        foreach ($schema->fields as $field) {
            if (EvidenceKind::forFieldType($field->type) instanceof EvidenceKind) {
                $existentes[$field->key] = count($this->submission->evidenceIds($field->key));
            }
        }

        $this->submission->loadMissing(['site:id,name', 'templateVersion.template:id,name']);

        return view('workflow::correction', [
            'fields' => array_values(array_filter(
                $schema->fields,
                fn (Field $field): bool => ! $field->visibleWhen instanceof VisibilityCondition
                    || $field->visibleWhen->isSatisfiedBy($this->answers),
            )),
            'existingEvidence' => $existentes,
            'rejectionComment' => SubmissionTransition::query()
                ->where('submission_id', $this->submission->getKey())
                ->where('to_state', Rejected::$name)
                ->latest('id')
                ->value('comment'),
        ]);
    }

    private function schema(): FormSchema
    {
        /** @var TemplateVersion $version */
        $version = TemplateVersion::query()->findOrFail($this->submission->template_version_id);

        return $version->formSchema();
    }

    /**
     * La respuesta guardada como la esperan los controles. Los si/no viajan
     * como '1'/'0' (radios) y la evidencia no se pone aqui: se conserva salvo
     * que se suba otra.
     *
     * @return array<string, mixed>
     */
    private function formStateFrom(Submission $submission): array
    {
        $estado = [];

        foreach ($this->schema()->fields as $field) {
            if (! array_key_exists($field->key, $submission->data)
                || EvidenceKind::forFieldType($field->type) instanceof EvidenceKind) {
                continue;
            }

            $valor = $submission->data[$field->key];

            $estado[$field->key] = $field->type === FieldType::Boolean && is_bool($valor)
                ? ($valor ? '1' : '0')
                : $valor;
        }

        return $estado;
    }
}
