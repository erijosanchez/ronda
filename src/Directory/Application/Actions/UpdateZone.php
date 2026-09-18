<?php

declare(strict_types=1);

namespace Ronda\Directory\Application\Actions;

use Ronda\Directory\Application\Data\ZoneData;
use Ronda\Directory\Domain\Exceptions\CannotDeleteZone;
use Ronda\Directory\Domain\Models\Zone;

/**
 * Modifica una zona. RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * Lo unico delicado es la jerarquia: colgar una zona de una de sus propias
 * hijas crearia un ciclo, y a partir de ahi recorrer el arbol no termina nunca.
 * La base no puede impedirlo, asi que se impide aqui.
 */
final readonly class UpdateZone
{
    public function __invoke(Zone $zone, ZoneData $data): Zone
    {
        $this->guardHierarchy($zone, $data->parentId);

        $zone->update($data->toAttributes());

        return $zone->refresh();
    }

    private function guardHierarchy(Zone $zone, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        if ($parentId === $zone->getKey()) {
            throw CannotDeleteZone::ownParent();
        }

        // Se sube por la rama del padre propuesto: si aparece esta zona, el
        // padre esta por debajo y se formaria un ciclo.
        $visitadas = [];
        $actual = Zone::query()->find($parentId);

        while ($actual instanceof Zone) {
            if ($actual->getKey() === $zone->getKey()) {
                throw CannotDeleteZone::parentInOwnBranch();
            }

            // Cinturon por si la base ya tuviera un ciclo de antes.
            if (in_array($actual->getKey(), $visitadas, true)) {
                return;
            }

            $visitadas[] = $actual->getKey();
            $actual = $actual->parent_id === null ? null : Zone::query()->find($actual->parent_id);
        }
    }
}
