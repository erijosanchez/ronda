<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Csv;

/**
 * Un problema concreto en una fila. RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * Fila, columna, motivo y el valor que lo provoco. Con menos que eso, quien
 * importa tiene que adivinar: «hay un error en el archivo» no arregla nada.
 */
final readonly class ImportIssue
{
    public function __construct(
        public int $row,
        public string $column,
        public ImportIssueReason $reason,
        public string $value = '',
    ) {}
}
