<?php

declare(strict_types=1);

namespace Ronda\Directory\Application\Data;

use Ronda\Identity\Domain\RoleName;

/**
 * Asignacion de una persona a una sede. RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * El rol es el que esa persona tiene EN ESA SEDE: la misma puede ser encargada
 * en una y supervisora en otra. Es opcional; sin el, la persona ve la sede pero
 * sus permisos salen solo de sus roles generales.
 */
final readonly class SiteAssignmentData
{
    public function __construct(
        public int $siteId,
        public ?int $positionId = null,
        public ?RoleName $role = null,
    ) {}

    /**
     * Columnas de la tabla intermedia para esta asignacion.
     *
     * @return array<string, int|string|null>
     */
    public function toPivot(): array
    {
        return [
            'position_id' => $this->positionId,
            'role' => $this->role?->value,
        ];
    }
}
