<?php

declare(strict_types=1);

namespace Ronda\Workflow\Domain\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Alguien tomo un envio para revisarlo. RONDA-PLAN-MAESTRO.md sec. 9.4
 *
 * Se despacha despues del commit: quien escuche (Notifications) no puede
 * avisar de algo que la transaccion termino deshaciendo.
 */
final readonly class SubmissionTaken implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $submissionId,
        public int $actorId,
    ) {}
}
