<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Application\Data;

use Ronda\Scheduling\Domain\ScheduleScope;

/**
 * Datos de una programacion, ya validados. RONDA-PLAN-MAESTRO.md sec. 8.3
 */
final readonly class ScheduleData
{
    /**
     * @param  list<int>  $siteIds  solo para el alcance `sites`
     */
    public function __construct(
        public int $templateId,
        public string $name,
        public ScheduleScope $scope,
        public string $rrule,
        public string $windowStart,
        public string $windowEnd,
        public string $startsOn,
        public ?string $endsOn = null,
        public int $toleranceMinutes = 0,
        public bool $skipHolidays = true,
        public ?int $zoneId = null,
        public array $siteIds = [],
    ) {}
}
