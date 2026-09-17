<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Domain;

/**
 * A que sedes alcanza una programacion. RONDA-PLAN-MAESTRO.md sec. 8.3
 */
enum ScheduleScope: string
{
    /** Todas las sedes del cliente, incluidas las que se den de alta despues. */
    case AllSites = 'all_sites';

    /** Las sedes de una zona, en el momento de materializar. */
    case Zone = 'zone';

    /** Una lista cerrada de sedes. */
    case Sites = 'sites';

    public function label(): string
    {
        return __('schedule-scope.'.$this->value);
    }
}
