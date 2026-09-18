<?php

declare(strict_types=1);

namespace Ronda\Directory\Application\Actions;

use Ronda\Directory\Application\Data\PositionData;
use Ronda\Directory\Domain\Models\Position;

/**
 * Modifica un cargo. RONDA-PLAN-MAESTRO.md sec. 8.3
 */
final readonly class UpdatePosition
{
    public function __invoke(Position $position, PositionData $data): Position
    {
        $position->update($data->toAttributes());

        return $position->refresh();
    }
}
