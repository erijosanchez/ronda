<?php

declare(strict_types=1);

namespace Ronda\Api\Domain\Webhooks;

/**
 * Eventos a los que un cliente puede suscribirse.
 * RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * Son los del plan y ninguno mas. La tentacion de publicar «todo lo que pasa»
 * acaba en integraciones que dependen de detalles internos: el dia que cambie
 * como se llama un estado, se rompe la integracion de un cliente.
 *
 * El nombre viaja en el cuerpo del envio y es contrato publico: no se renombra
 * sin una version nueva.
 */
enum WebhookEvent: string
{
    case SubmissionSubmitted = 'submission.submitted';
    case SubmissionApproved = 'submission.approved';
    case SubmissionRejected = 'submission.rejected';
    case ObligationMissed = 'obligation.missed';

    public function label(): string
    {
        return __('webhook-events.'.$this->value);
    }
}
