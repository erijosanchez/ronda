<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Ronda\Scheduling\Domain\Models\Schedule;

/**
 * Deja de pedir una programacion sin borrarla.
 *
 * Se conserva todo su historial. Lo que todavia no abrio se descarta: una sede
 * no tiene por que ver en su lista una entrega que ya nadie espera.
 */
final readonly class PauseSchedule
{
    public function __construct(
        private ConnectionInterface $connection,
        private ReplanObligations $replan,
    ) {}

    public function __invoke(Schedule $schedule): Schedule
    {
        return $this->connection->transaction(function () use ($schedule): Schedule {
            $schedule->update(['active' => false]);

            ($this->replan)($schedule);

            return $schedule;
        });
    }
}
