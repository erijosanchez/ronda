<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Ronda\Scheduling\Application\Data\ScheduleData;
use Ronda\Scheduling\Domain\Exceptions\InvalidSchedule;
use Ronda\Scheduling\Domain\Models\Schedule;
use Ronda\Scheduling\Domain\ScheduleScope;
use Ronda\Scheduling\Domain\ValueObjects\Recurrence;

/**
 * Modifica una programacion y replanifica lo que todavia no abrio.
 * RONDA-PLAN-MAESTRO.md sec. 9.1
 *
 * Recibe la programacion ya resuelta: quien la busca es quien aplica la Policy.
 *
 * La plantilla no cambia. El cumplimiento de una programacion se lee contra la
 * plantilla que pedia; cambiarla a mitad mezclaria dos historias en una.
 */
final readonly class UpdateSchedule
{
    public function __construct(
        private ConnectionInterface $connection,
        private ValidateSchedule $validate,
        private ReplanObligations $replan,
    ) {}

    public function __invoke(Schedule $schedule, ScheduleData $data): Schedule
    {
        if ($data->templateId !== $schedule->template_id) {
            throw InvalidSchedule::templateChanged();
        }

        ($this->validate)($data);

        return $this->connection->transaction(function () use ($schedule, $data): Schedule {
            $schedule->update([
                'name' => $data->name,
                'scope' => $data->scope->value,
                'zone_id' => $data->scope === ScheduleScope::Zone ? $data->zoneId : null,
                'rrule' => Recurrence::fromString($data->rrule)->rule,
                'window_start' => $data->windowStart,
                'window_end' => $data->windowEnd,
                'tolerance_minutes' => $data->toleranceMinutes,
                'skip_holidays' => $data->skipHolidays,
                'starts_on' => $data->startsOn,
                'ends_on' => $data->endsOn,
            ]);

            // Fuera del alcance `sites` la lista no significa nada; dejarla
            // guardada haria que volviera a aparecer al cambiar de alcance.
            $schedule->sites()->sync($data->scope === ScheduleScope::Sites ? $data->siteIds : []);

            $schedule->refresh();

            ($this->replan)($schedule);

            return $schedule;
        });
    }
}
