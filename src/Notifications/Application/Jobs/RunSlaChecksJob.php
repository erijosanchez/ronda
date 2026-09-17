<?php

declare(strict_types=1);

namespace Ronda\Notifications\Application\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Ronda\Notifications\Application\Actions\EscalateMissedObligations;
use Ronda\Notifications\Application\Actions\EscalateStaleReviews;
use Ronda\Notifications\Application\Actions\SendObligationReminders;

/**
 * El repaso de SLA de UN tenant. RONDA-PLAN-MAESTRO.md sec. 9.4
 *
 * Corre despues del motor de obligaciones: primero se cierra lo vencido
 * (`obligations:materialize` marca lo incumplido) y luego se avisa. Al reves,
 * el escalamiento de un incumplimiento esperaria una hora entera.
 *
 * Es repetible: cada aviso se anota en `sla_events` antes de mandarse, asi que
 * relanzar el job no reenvia nada.
 */
final class RunSlaChecksJob implements ShouldQueue
{
    use Queueable;

    public function handle(
        SendObligationReminders $reminders,
        EscalateMissedObligations $missed,
        EscalateStaleReviews $reviews,
    ): void {
        $reminders();
        $missed();
        $reviews();
    }
}
