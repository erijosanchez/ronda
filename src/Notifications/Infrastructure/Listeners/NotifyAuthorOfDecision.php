<?php

declare(strict_types=1);

namespace Ronda\Notifications\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Identity\Domain\Models\User;
use Ronda\Notifications\Application\Actions\SendNotification;
use Ronda\Notifications\Application\Data\NotificationMessage;
use Ronda\Notifications\Application\Queries\TenantUrlQuery;
use Ronda\Notifications\Domain\NotificationTopic;
use Ronda\Submissions\Domain\Models\Submission;
use Ronda\Submissions\Domain\States\Rejected;
use Ronda\Workflow\Domain\Events\SubmissionApproved;
use Ronda\Workflow\Domain\Events\SubmissionRejected;
use Ronda\Workflow\Domain\Models\SubmissionTransition;

/**
 * Le dice a quien entrego como acabo su reporte. RONDA-PLAN-MAESTRO.md sec. 9.4
 *
 * El rechazo lleva el motivo dentro: si hay que abrir la aplicacion para saber
 * que corregir, se corrige mas tarde.
 */
final readonly class NotifyAuthorOfDecision implements ShouldQueue
{
    public function __construct(
        private SendNotification $send,
        private TenantUrlQuery $url,
    ) {}

    public function handle(SubmissionApproved|SubmissionRejected $event): void
    {
        $envio = Submission::query()->with(['site', 'templateVersion.template'])->find($event->submissionId);

        if (! $envio instanceof Submission) {
            return;
        }

        $autor = User::query()->find($envio->author_id);

        if (! $autor instanceof User) {
            return;
        }

        $sede = $envio->site;
        $plantilla = $envio->templateVersion?->template;

        if (! $sede instanceof Site || ! $plantilla instanceof Template) {
            return;
        }

        $aprobado = $event instanceof SubmissionApproved;

        ($this->send)([$autor], new NotificationMessage(
            topic: $aprobado ? NotificationTopic::SubmissionApproved : NotificationTopic::SubmissionRejected,
            title: $aprobado
                ? __('Approved: :template', ['template' => $plantilla->name])
                : __('To correct: :template', ['template' => $plantilla->name]),
            body: $aprobado
                ? __('«:template» of :site was approved.', ['template' => $plantilla->name, 'site' => $sede->name])
                : __('«:template» of :site was rejected: :reason', [
                    'template' => $plantilla->name,
                    'site' => $sede->name,
                    'reason' => $this->reason($envio),
                ]),
            url: ($this->url)('submissions.show', ['submission' => $envio->getKey()]),
            meta: ['site' => $sede->name, 'submission_id' => $envio->getKey()],
        ));
    }

    private function reason(Submission $submission): string
    {
        $comentario = SubmissionTransition::query()
            ->where('submission_id', $submission->getKey())
            ->where('to_state', Rejected::$name)
            ->latest('id')
            ->value('comment');

        return is_string($comentario) ? $comentario : '';
    }
}
