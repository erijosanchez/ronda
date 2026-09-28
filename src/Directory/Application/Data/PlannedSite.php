<?php

declare(strict_types=1);

namespace Ronda\Directory\Application\Data;

/**
 * Una sede lista para escribirse. RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * `existingId` es lo que separa un alta de una actualizacion. Se decide al
 * analizar y no al escribir para que la pantalla pueda decir «12 nuevas, 3 se
 * actualizan» ANTES de tocar la base: importar es la clase de operacion en la
 * que nadie quiere enterarse despues.
 */
final readonly class PlannedSite
{
    public function __construct(
        public SiteData $data,
        public ?int $existingId = null,
    ) {}

    public function isUpdate(): bool
    {
        return $this->existingId !== null;
    }
}
