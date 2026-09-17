<?php

declare(strict_types=1);

namespace Ronda\Submissions\Domain\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Llego un reporte. RONDA-PLAN-MAESTRO.md sec. 6.2
 *
 * Es el punto del que cuelga todo lo que pasa despues de entregar: avisar a
 * quien revisa hoy, recalcular KPI y auditar manana.
 *
 * Se despacha despues del commit: nadie debe enterarse de una entrega que la
 * transaccion termino deshaciendo.
 */
final readonly class SubmissionSubmitted implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $submissionId,
        public int $authorId,
    ) {}
}
