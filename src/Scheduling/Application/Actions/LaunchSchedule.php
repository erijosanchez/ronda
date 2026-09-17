<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Ronda\Scheduling\Application\Data\ScheduleData;
use Ronda\Scheduling\Domain\Models\Schedule;

/**
 * Crea una programacion y la pone a producir obligaciones en el acto.
 *
 * Es lo que hace la pantalla. Sin la materializacion inmediata, quien acaba de
 * programar el arqueo de hoy no lo veria en la lista de pendientes hasta la
 * siguiente pasada del job, y pensaria que no funciono.
 *
 * Todo en una transaccion: si materializar falla, tampoco queda la
 * programacion.
 */
final readonly class LaunchSchedule
{
    public function __construct(
        private ConnectionInterface $connection,
        private CreateSchedule $create,
        private ReplanObligations $replan,
    ) {}

    public function __invoke(ScheduleData $data): Schedule
    {
        return $this->connection->transaction(function () use ($data): Schedule {
            $schedule = ($this->create)($data);

            ($this->replan)($schedule);

            return $schedule;
        });
    }
}
