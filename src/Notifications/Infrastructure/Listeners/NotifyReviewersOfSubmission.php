<?php

declare(strict_types=1);

namespace Ronda\Notifications\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Notifications\Application\Actions\SendNotification;
use Ronda\Notifications\Application\Data\NotificationMessage;
use Ronda\Notifications\Application\Queries\NotificationRecipientsQuery;
use Ronda\Notifications\Application\Queries\TenantUrlQuery;
use Ronda\Notifications\Domain\Audience;
use Ronda\Notifications\Domain\NotificationTopic;
use Ronda\Submissions\Domain\Events\SubmissionSubmitted;
use Ronda\Submissions\Domain\Models\Submission;

/**
 * Avisa a quien revisa esa sede de que le llego algo.
 * RONDA-PLAN-MAESTRO.md sec. 6.2
 *
 * En cola: la sede no tiene por que esperar a que se manden los avisos para ver
 * su reporte entregado.
 *
 * No pasa por NotifyOnce: cada entrega (y cada correccion) es un aviso nuevo, y
 * eso es exactamente lo que se quiere.
 */
final readonly class NotifyReviewersOfSubmission implements ShouldQueue
{
    public function __construct(
        private NotificationRecipientsQuery $recipients,
        private SendNotification $send,
        private TenantUrlQuery $url,
    ) {}

    public function handle(SubmissionSubmitted $event): void
    {
        $envio = Submission::query()->with(['site', 'templateVersion.template', 'author'])->find($event->submissionId);

        if (! $envio instanceof Submission) {
            return;
        }

        $sede = $envio->site;
        $plantilla = $envio->templateVersion?->template;

        if (! $sede instanceof Site || ! $plantilla instanceof Template) {
            return;
        }

        ($this->send)(
            $this->recipients->forSubmission(Audience::Reviewers, $envio),
            new NotificationMessage(
                topic: NotificationTopic::SubmissionAwaitingReview,
                title: __('To review: :template', ['template' => $plantilla->name]),
                body: __(':author submitted «:template» from :site.', [
                    'author' => (string) $envio->author?->name,
                    'template' => $plantilla->name,
                    'site' => $sede->name,
                ]),
                url: ($this->url)('submissions.show', ['submission' => $envio->getKey()]),
                meta: ['site' => $sede->name, 'submission_id' => $envio->getKey()],
            ),
        );
    }
}
