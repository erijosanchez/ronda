<?php

declare(strict_types=1);

namespace Ronda\Notifications\Domain;

/**
 * De que avisa Ronda. RONDA-PLAN-MAESTRO.md sec. 9.3 y 9.4
 *
 * Lista cerrada a proposito: cada tema tiene su escalera, sus canales y su
 * texto. Un aviso que no esta aqui no se manda.
 */
enum NotificationTopic: string
{
    /** Queda poco para que venza una entrega y sigue pendiente. */
    case ObligationDueSoon = 'obligation_due_soon';

    /** La entrega no llego y la obligacion quedo incumplida. */
    case ObligationMissed = 'obligation_missed';

    /** Llego un envio a la bandeja de revision. */
    case SubmissionAwaitingReview = 'submission_awaiting_review';

    /** Un envio lleva demasiado tiempo esperando decision. */
    case ReviewOverdue = 'review_overdue';

    case SubmissionApproved = 'submission_approved';

    case SubmissionRejected = 'submission_rejected';

    /** Termino (o fallo) una exportacion encargada. */
    case ExportReady = 'export_ready';

    /**
     * Alguien de Ronda entro en la cuenta del cliente para darle soporte.
     *
     * Este NO es un aviso de operacion: es de transparencia (sec. 15.4). Que
     * el cliente se entere no es cortesia, es lo que hace legitima la
     * suplantacion.
     */
    case ImpersonationStarted = 'impersonation_started';

    public function label(): string
    {
        return __('notification-topics.'.$this->value);
    }

    /**
     * Los temas que se escalan: si nadie hace nada, el siguiente aviso sube de
     * nivel.
     */
    public function escalates(): bool
    {
        return match ($this) {
            self::ObligationMissed, self::ReviewOverdue => true,
            default => false,
        };
    }
}
