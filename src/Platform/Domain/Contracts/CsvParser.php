<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Contracts;

use Ronda\Platform\Domain\Csv\CsvTable;
use Ronda\Platform\Domain\Exceptions\UnreadableCsv;

/**
 * Quien convierte un archivo en filas. RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * El contrato vive en Domain y la implementacion que abre archivos en
 * Infrastructure, porque Application no puede depender de Infrastructure
 * (deptrac lo verifica). Es la misma forma que `PlanProvider`.
 */
interface CsvParser
{
    /**
     * @param  int  $maxRows  tope de filas; pasarse es un error, no un recorte
     *
     * @throws UnreadableCsv
     */
    public function parse(string $path, int $maxRows): CsvTable;
}
