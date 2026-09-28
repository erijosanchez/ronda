<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Csv;

/**
 * Lo que hizo una importacion. RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * Se distingue lo creado de lo actualizado a proposito: subir dos veces el
 * mismo archivo es lo mas normal del mundo —alguien corrige una celda y
 * reintenta—, y «0 nuevas, 120 actualizadas» es la prueba de que no se
 * duplico nada.
 */
final readonly class ImportResult
{
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
    ) {}

    public function total(): int
    {
        return $this->created + $this->updated;
    }
}
