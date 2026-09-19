<?php

declare(strict_types=1);

namespace Ronda\Platform\Application\Policies;

use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\PermissionName;

/**
 * Quien ve el plan y el consumo del cliente.
 * RONDA-PLAN-MAESTRO.md sec. 15.1
 *
 * No cuelga de un modelo —el plan es del cliente entero— asi que se registra
 * como habilidad suelta (`view-plan`), con la decision aqui (regla 4).
 *
 * De momento lo gobierna `site.manage`: quien monta la operacion es quien
 * sufre los limites y quien pide subir de plan. Cuando llegue la facturacion
 * habra permisos propios (`billing.view`, `billing.manage`), porque ver una
 * factura y cambiar una tarjeta no son lo mismo que crear una sede.
 */
final readonly class PlanPolicy
{
    public function view(User $actor): bool
    {
        return $actor->hasPermissionTo(PermissionName::SiteManage->value);
    }
}
