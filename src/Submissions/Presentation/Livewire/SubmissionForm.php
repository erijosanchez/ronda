<?php

declare(strict_types=1);

namespace Ronda\Submissions\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Forms\Domain\ValueObjects\Field;
use Ronda\Forms\Domain\ValueObjects\FormSchema;
use Ronda\Forms\Domain\ValueObjects\VisibilityCondition;
use Ronda\Identity\Domain\Models\User;
use Ronda\Scheduling\Domain\Models\Obligation;
use Ronda\Submissions\Application\Actions\SubmitReport;
use Ronda\Submissions\Domain\Exceptions\CannotSubmit;
use Ronda\Submissions\Domain\Exceptions\InvalidAnswers;
use Ronda\Submissions\Presentation\Livewire\Concerns\CollectsEvidence;

/**
 * Rellenar y entregar un reporte. RONDA-PLAN-MAESTRO.md sec. 9.1 y 9.5
 *
 * Valida, invoca la Action y devuelve (regla 1). La validacion de verdad es la
 * del dominio (AnswerValidator y StoreEvidence): esta pantalla solo traduce sus
 * errores a cada campo y convierte lo que llega del navegador en EvidenceUpload
 * (CollectsEvidence).
 *
 * Las condiciones de visibilidad se evaluan en el servidor en cada render, con
 * las respuestas del momento. El unico JavaScript es el del bundle (firma y
 * ubicacion, en resources/js/evidence.js): la CSP no admite scripts en linea
 * (ADR 0011).
 */
final class SubmissionForm extends Component
{
    use CollectsEvidence;

    public Obligation $obligation;

    /**
     * Respuestas en curso, por clave de campo.
     *
     * @var array<string, mixed>
     */
    public array $answers = [];

    public function mount(Obligation $obligation): void
    {
        $this->authorize('submit', $obligation);

        $this->obligation = $obligation;
    }

    public function submit(): void
    {
        $this->authorize('submit', $this->obligation);

        /** @var User $author */
        $author = auth()->user();

        try {
            $submission = resolve(SubmitReport::class)(
                $this->obligation,
                $author,
                $this->answers,
                evidence: $this->collectedEvidence(),
            );
        } catch (InvalidAnswers $e) {
            foreach ($e->errors as $key => $message) {
                $this->addError("answers.{$key}", $message);
            }

            return;
        } catch (CannotSubmit $e) {
            $this->addError('obligation', $e->getMessage());

            return;
        }

        session()->flash('status', __('Report submitted.'));
        $this->redirectRoute('submissions.show', ['submission' => $submission], navigate: true);
    }

    public function render(): View
    {
        $schema = $this->schema();

        return view('submissions::form', [
            'fields' => $schema instanceof FormSchema ? array_values(array_filter(
                $schema->fields,
                fn (Field $field): bool => ! $field->visibleWhen instanceof VisibilityCondition
                    || $field->visibleWhen->isSatisfiedBy($this->answers),
            )) : [],
            'site' => $this->obligation->site,
            'template' => Template::query()->find($this->obligation->template_id),
        ]);
    }

    private function schema(): ?FormSchema
    {
        $template = Template::query()->with('currentVersion')->find($this->obligation->template_id);

        return $template?->currentVersion?->formSchema();
    }
}
