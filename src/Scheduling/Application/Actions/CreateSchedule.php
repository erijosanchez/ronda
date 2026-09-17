<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Scheduling\Application\Data\ScheduleData;
use Ronda\Scheduling\Domain\Exceptions\InvalidSchedule;
use Ronda\Scheduling\Domain\Models\Schedule;
use Ronda\Scheduling\Domain\ScheduleScope;
use Ronda\Scheduling\Domain\ValueObjects\Recurrence;
use Ronda\Scheduling\Domain\ValueObjects\TimeWindow;

/**
 * Crea una programacion. RONDA-PLAN-MAESTRO.md sec. 9.1
 *
 * La regla y la ventana se validan construyendo sus value objects ANTES de
 * guardar: una RRULE que no se puede expandir guardada hoy es un job que
 * revienta de madrugada dentro de un mes.
 *
 * Escribe en `schedules` y, con el alcance `sites`, en `schedule_site`: va en
 * transaccion (regla 3).
 */
final readonly class CreateSchedule
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function __invoke(ScheduleData $data): Schedule
    {
        Recurrence::fromString($data->rrule);
        TimeWindow::between($data->windowStart, $data->windowEnd);

        $this->guardTemplate($data->templateId);
        $this->guardScope($data);

        return $this->connection->transaction(function () use ($data): Schedule {
            $schedule = Schedule::create([
                'template_id' => $data->templateId,
                'name' => $data->name,
                'scope' => $data->scope->value,
                'zone_id' => $data->scope === ScheduleScope::Zone ? $data->zoneId : null,
                'rrule' => Recurrence::fromString($data->rrule)->rule,
                'window_start' => $data->windowStart,
                'window_end' => $data->windowEnd,
                'tolerance_minutes' => $data->toleranceMinutes,
                'skip_holidays' => $data->skipHolidays,
                'active' => true,
                'starts_on' => $data->startsOn,
                'ends_on' => $data->endsOn,
            ]);

            if ($data->scope === ScheduleScope::Sites) {
                $schedule->sites()->sync($data->siteIds);
            }

            return $schedule;
        });
    }

    /**
     * Solo se programa lo publicado: una plantilla en borrador no tiene version
     * con la que responder.
     */
    private function guardTemplate(int $templateId): void
    {
        $template = Template::query()->find($templateId);

        if (! $template instanceof Template || ! $template->status->canBeScheduled()) {
            throw InvalidSchedule::templateNotPublished();
        }
    }

    private function guardScope(ScheduleData $data): void
    {
        if ($data->scope === ScheduleScope::Zone && $data->zoneId === null) {
            throw InvalidSchedule::zoneRequired();
        }

        if ($data->scope === ScheduleScope::Sites && $data->siteIds === []) {
            throw InvalidSchedule::sitesRequired();
        }

        if ($data->endsOn !== null && $data->endsOn < $data->startsOn) {
            throw InvalidSchedule::endsBeforeStart();
        }
    }
}
