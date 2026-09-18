<?php

declare(strict_types=1);

namespace Ronda\Directory\Application\Actions;

use Ronda\Directory\Domain\Exceptions\CannotDeleteZone;
use Ronda\Directory\Domain\Models\Zone;
use Ronda\Scheduling\Domain\Models\Schedule;

/**
 * Borra una zona, si no queda nada colgando. RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * Borrado logico: una zona borrada no se lleva por delante el historial de lo
 * que se midio bajo ella.
 *
 * Se comprueba antes de borrar en vez de dejar que salte la restriccion de la
 * base, porque el usuario necesita saber QUE hay que mover. Y se mira tambien
 * `schedules`, que la base no restringe con borrado logico: una programacion
 * por zona se quedaria apuntando a una zona que ya no existe y dejaria de
 * materializar en silencio.
 */
final readonly class DeleteZone
{
    public function __invoke(Zone $zone): void
    {
        $sedes = $zone->sites()->count();

        if ($sedes > 0) {
            throw CannotDeleteZone::hasSites($sedes);
        }

        $hijas = $zone->children()->count();

        if ($hijas > 0) {
            throw CannotDeleteZone::hasChildren($hijas);
        }

        $programaciones = Schedule::query()->where('zone_id', $zone->getKey())->count();

        if ($programaciones > 0) {
            throw CannotDeleteZone::hasSchedules($programaciones);
        }

        $zone->delete();
    }
}
