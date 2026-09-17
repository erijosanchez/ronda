<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Domain\States;

final class Fulfilled extends ObligationStatus
{
    public static string $name = 'fulfilled';

    public function countsTowardsCompliance(): bool
    {
        return true;
    }
}
