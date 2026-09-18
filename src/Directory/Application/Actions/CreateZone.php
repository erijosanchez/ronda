<?php

declare(strict_types=1);

namespace Ronda\Directory\Application\Actions;

use Ronda\Directory\Application\Data\ZoneData;
use Ronda\Directory\Domain\Models\Zone;

/**
 * Crea una zona. RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * Una sola tabla, asi que no necesita transaccion.
 */
final readonly class CreateZone
{
    public function __invoke(ZoneData $data): Zone
    {
        return Zone::create($data->toAttributes());
    }
}
