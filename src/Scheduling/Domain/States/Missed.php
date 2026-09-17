<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Domain\States;

final class Missed extends ObligationStatus
{
    public static string $name = 'missed';

    public function countsTowardsCompliance(): bool
    {
        return true;
    }
}
