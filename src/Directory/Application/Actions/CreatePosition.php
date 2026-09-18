<?php

declare(strict_types=1);

namespace Ronda\Directory\Application\Actions;

use Ronda\Directory\Application\Data\PositionData;
use Ronda\Directory\Domain\Models\Position;

/**
 * Crea un cargo del organigrama. RONDA-PLAN-MAESTRO.md sec. 8.3
 */
final readonly class CreatePosition
{
    public function __invoke(PositionData $data): Position
    {
        return Position::create($data->toAttributes());
    }
}
