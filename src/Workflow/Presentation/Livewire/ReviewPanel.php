<?php

declare(strict_types=1);

namespace Ronda\Workflow\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Ronda\Identity\Domain\Models\User;
use Ronda\Submissions\Domain\Models\Submission;
use Ronda\Submissions\Domain\States\Rejected;
use Ronda\Submissions\Domain\States\Submitted;
use Ronda\Submissions\Domain\States\UnderReview;
use Ronda\Workflow\Application\Actions\ApproveSubmission;
use Ronda\Workflow\Application\Actions\CommentOnSubmission;
use Ronda\Workflow\Application\Actions\RejectSubmission;
use Ronda\Workflow\Application\Actions\TakeSubmission;
use Ronda\Workflow\Domain\Exceptions\CannotReview;

/**
 * Revision, historial y conversacion de un envio, dentro de su ficha.
 * RONDA-PLAN-MAESTRO.md sec. 9.4
 *
 * Valida, invoca la Action y devuelve (regla 1). La Policy decide que botones
 * existen; las reglas del flujo (no revisar lo propio, no decidir lo que tomo
 * otro) las aplica la Action y aqui solo se muestran sus mensajes.
 *
 * Tras cada decision recarga la ficha entera: el estado, la evidencia visible y
 * las URLs firmadas cambian con ella.
 */
final class ReviewPanel extends Component
{
    public Submission $submission;

    public string $decisionComment = '';

    public string $newComment = '';

    public function mount(Submission $submission): void
    {
        $this->authorize('view', $submission);

        $this->submission = $submission;
    }

    public function take(): void
    {
        $this->authorize('review', $this->submission);

        $this->run(fn (User $actor): Submission => resolve(TakeSubmission::class)($this->submission, $actor), __('Submission taken for review.'));
    }

    public function approve(): void
    {
        $this->authorize('approve', $this->submission);

        $this->run(
            fn (User $actor): Submission => resolve(ApproveSubmission::class)($this->submission, $actor, $this->decisionComment),
            __('Submission approved.'),
        );
    }

    public function reject(): void
    {
        $this->authorize('review', $this->submission);

        $this->validate(
            ['decisionComment' => ['required', 'string', 'max:2000']],
            [],
            ['decisionComment' => __('reason')],
        );

        $this->run(
            fn (User $actor): Submission => resolve(RejectSubmission::class)($this->submission, $actor, $this->decisionComment),
            __('Submission rejected. The site can now correct it.'),
        );
    }

    public function comment(): void
    {
        $this->authorize('comment', $this->submission);

        $this->validate(
            ['newComment' => ['required', 'string', 'max:'.CommentOnSubmission::MAX_LENGTH]],
            [],
            ['newComment' => __('comment')],
        );

        /** @var User $actor */
        $actor = auth()->user();

        resolve(CommentOnSubmission::class)($this->submission, $actor, $this->newComment);

        $this->newComment = '';
    }

    public function render(): View
    {
        $submission = $this->submission->fresh(['reviewer']) ?? $this->submission;
        /** @var User $viewer */
        $viewer = auth()->user();

        $esAutor = $submission->author_id === $viewer->getKey();
        $tomadoPorOtro = $submission->state instanceof UnderReview && $submission->reviewer_id !== $viewer->getKey();
        $decidible = ! $esAutor && ! $tomadoPorOtro
            && ($submission->state instanceof Submitted || $submission->state instanceof UnderReview);

        return view('workflow::review-panel', [
            'current' => $submission,
            'transitions' => $submission->transitions()->with('actor:id,name')->get(),
            'comments' => $submission->comments()->with('author:id,name')->get(),
            'revisions' => $submission->revisions()->get(),
            'canTake' => $decidible && $submission->state instanceof Submitted && $viewer->can('review', $submission),
            'canReject' => $decidible && $viewer->can('review', $submission),
            'canApprove' => $decidible && $viewer->can('approve', $submission),
            'canCorrect' => $submission->state instanceof Rejected && $viewer->can('correct', $submission),
            'canComment' => $viewer->can('comment', $submission),
            'takenByOther' => $tomadoPorOtro,
        ]);
    }

    /**
     * @param  callable(User): Submission  $accion
     */
    private function run(callable $accion, string $mensaje): void
    {
        /** @var User $actor */
        $actor = auth()->user();

        try {
            $accion($actor);
        } catch (CannotReview $e) {
            $this->addError('review', $e->getMessage());

            return;
        }

        session()->flash('status', $mensaje);
        $this->redirectRoute('submissions.show', ['submission' => $this->submission], navigate: true);
    }
}
