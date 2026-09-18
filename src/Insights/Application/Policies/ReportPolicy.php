<?php

declare(strict_types=1);

namespace Ronda\Insights\Application\Policies;

use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\PermissionName;

/**
 * Quien puede ver los indicadores. RONDA-PLAN-MAESTRO.md sec. 10.3
 *
 * No cuelga de un modelo: los KPI no son un registro, son una lectura del
 * conjunto. Se registra como habilidad suelta (`view-reports`) en
 * AuthorizationServiceProvider, pero vive aqui porque la comprobacion de
 * permisos solo ocurre en Policies (regla 4).
 *
 * Que sedes entran en esa lectura no lo decide este permiso, sino la frontera
 * por sede de las consultas: un encargado con `report.view` veria los
 * indicadores de SUS sedes.
 */
final readonly class ReportPolicy
{
    public function view(User $actor): bool
    {
        return $actor->hasPermissionTo(PermissionName::ReportView->value);
    }
}
