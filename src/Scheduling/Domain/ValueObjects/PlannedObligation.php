<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Domain\ValueObjects;

use Carbon\CarbonImmutable;

/**
 * Una obligacion calculada y todavia no guardada.
 */
final readonly class PlannedObligation
{
    public function __construct(
        public int $siteId,
        public string $occurrenceDate,
        public CarbonImmutable $opensAt,
        public CarbonImmutable $dueAt,
        public CarbonImmutable $closesAt,
    ) {}
}
