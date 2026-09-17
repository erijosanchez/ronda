<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Domain\States;

final class Pending extends ObligationStatus
{
    public static string $name = 'pending';
}
