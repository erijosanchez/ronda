<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Application\Actions;

use Carbon\CarbonImmutable;
use Ronda\Scheduling\Domain\Models\Obligation;
use Ronda\Scheduling\Domain\States\Missed;
use Ronda\Scheduling\Domain\States\Pending;

/**
 * Da por incumplidas las obligaciones cuyo cierre ya paso. ADR 0008.
 *
 * Con esto el incumplimiento es una fila con estado `missed`, no una ausencia
 * que haya que deducir, que es la idea entera del ADR.
 *
 * Es un UPDATE masivo que no pasa por la maquina de estados fila a fila, y es
 * seguro: el WHERE restringe a `pending`, y `pending -> missed` es una
 * transicion permitida, asi que no puede producir un estado imposible. Hacerlo
 * con transitionTo() costaria una consulta por obligacion en un parque de
 * miles, cada noche.
 */
final readonly class MarkMissedObligations
{
    /**
     * @return int cuantas se marcaron
     */
    public function __invoke(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now('UTC');

        return Obligation::query()
            ->where('status', Pending::$name)
            ->where('closes_at', '<', $now)
            ->update([
                'status' => Missed::$name,
                'updated_at' => $now,
            ]);
    }
}
