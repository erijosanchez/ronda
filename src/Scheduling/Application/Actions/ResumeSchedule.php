<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Ronda\Scheduling\Domain\Models\Schedule;

/**
 * Vuelve a pedir una programacion pausada.
 *
 * Retoma desde ahora: lo que no se pidio mientras estuvo pausada no se reclama
 * a posteriori, porque ninguna sede pudo cumplirlo.
 */
final readonly class ResumeSchedule
{
    public function __construct(
        private ConnectionInterface $connection,
        private ReplanObligations $replan,
    ) {}

    public function __invoke(Schedule $schedule): Schedule
    {
        return $this->connection->transaction(function () use ($schedule): Schedule {
            $schedule->update(['active' => true]);

            ($this->replan)($schedule);

            return $schedule;
        });
    }
}
