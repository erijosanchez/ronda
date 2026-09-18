<?php

declare(strict_types=1);

namespace Ronda\Directory\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Ronda\Directory\Domain\Exceptions\CannotDeletePosition;
use Ronda\Directory\Domain\Models\Position;

/**
 * Borra un cargo que ya no ocupa nadie. RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * Borrado logico, y solo si nadie lo tiene asignado en una sede: un cargo
 * borrado por debajo dejaria asignaciones apuntando a la nada.
 */
final readonly class DeletePosition
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function __invoke(Position $position): void
    {
        $asignaciones = $this->connection->table('user_site')
            ->where('position_id', $position->getKey())
            ->count();

        if ($asignaciones > 0) {
            throw CannotDeletePosition::inUse($asignaciones);
        }

        $position->delete();
    }
}
