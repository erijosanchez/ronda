<?php

declare(strict_types=1);

namespace Ronda\Api\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Ronda\Api\Application\Actions\QueueWebhook;
use Ronda\Api\Domain\Webhooks\WebhookEvent;
use Ronda\Scheduling\Domain\Events\ObligationMissed;
use Ronda\Scheduling\Domain\Models\Obligation;
use Ronda\Submissions\Domain\Events\SubmissionSubmitted;
use Ronda\Submissions\Domain\Models\Submission;
use Ronda\Workflow\Domain\Events\SubmissionApproved;
use Ronda\Workflow\Domain\Events\SubmissionRejected;

/**
 * Traduce lo que pasa dentro a avisos para fuera.
 * RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * Es el unico sitio que sabe que existe el catalogo publico de eventos. Quien
 * entrega o aprueba un reporte no tiene por que saber que alguien esta
 * escuchando: si este modulo se quitara, el resto seguiria funcionando igual.
 *
 * El cuerpo que sale es el MISMO que devuelve la API para ese recurso, con las
 * mismas claves. Dos formas distintas de decir lo mismo obligarian a quien
 * integra a escribir dos lectores.
 *
 * En cola: el aviso a un servidor ajeno no puede retrasar la respuesta a quien
 * acaba de entregar un reporte.
 */
final readonly class PublishWebhooks implements ShouldQueue
{
    public function __construct(
        private QueueWebhook $queue,
    ) {}

    public function submitted(SubmissionSubmitted $event): void
    {
        $this->forSubmission(WebhookEvent::SubmissionSubmitted, $event->submissionId);
    }

    public function approved(SubmissionApproved $event): void
    {
        $this->forSubmission(WebhookEvent::SubmissionApproved, $event->submissionId);
    }

    public function rejected(SubmissionRejected $event): void
    {
        $this->forSubmission(WebhookEvent::SubmissionRejected, $event->submissionId);
    }

    public function missed(ObligationMissed $event): void
    {
        $obligacion = Obligation::query()->find($event->obligationId);

        if (! $obligacion instanceof Obligation) {
            return;
        }

        ($this->queue)(WebhookEvent::ObligationMissed, [
            'id' => $obligacion->id,
            'site_id' => $obligacion->site_id,
            'template_id' => $obligacion->template_id,
            'occurrence_date' => $obligacion->occurrence_date?->toDateString(),
            'closes_at' => $obligacion->closes_at?->toIso8601String(),
        ]);
    }

    private function forSubmission(WebhookEvent $event, int $submissionId): void
    {
        $envio = Submission::query()->find($submissionId);

        if (! $envio instanceof Submission) {
            return;
        }

        ($this->queue)($event, [
            'id' => $envio->id,
            'state' => (string) $envio->state,
            'site_id' => $envio->site_id,
            'template_id' => $envio->template_id,
            'template_version_id' => $envio->template_version_id,
            'obligation_id' => $envio->obligation_id,
            'submitted_at' => $envio->submitted_at?->toIso8601String(),
            'is_late' => (bool) $envio->is_late,
        ]);
    }
}
