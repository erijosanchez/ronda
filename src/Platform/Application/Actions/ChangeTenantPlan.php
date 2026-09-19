<?php

declare(strict_types=1);

namespace Ronda\Platform\Application\Actions;

use Ronda\Platform\Domain\Exceptions\PlanNotAvailable;
use Ronda\Platform\Domain\Models\Plan;
use Ronda\Platform\Domain\Models\Tenant;
use Ronda\Platform\Domain\PlanCode;

/**
 * Cambia el plan de un cliente. RONDA-PLAN-MAESTRO.md sec. 15.1
 *
 * Escribe en la base CENTRAL: el plan es un acuerdo entre Ronda y el cliente.
 * Una sola tabla, asi que no abre transaccion (regla 3).
 *
 * No comprueba si el cliente PUEDE pasarse a ese plan —si pago, si su uso
 * actual cabe— porque eso lo decide la facturacion, que todavia no existe.
 * Cuando llegue, sera ella quien llame aqui; hoy llaman el back-office y las
 * pruebas.
 *
 * Bajar de plan no borra nada: un cliente que baja a Starter con cinco
 * plantillas se queda con las cinco y no puede crear la sexta. Borrarle
 * trabajo por cambiar de plan seria perder datos suyos por una decision
 * comercial.
 */
final readonly class ChangeTenantPlan
{
    /**
     * @throws PlanNotAvailable si el plan no esta cargado en el catalogo
     */
    public function __invoke(Tenant $tenant, PlanCode $code): Tenant
    {
        $plan = Plan::query()->where('code', $code->value)->first();

        if (! $plan instanceof Plan) {
            throw PlanNotAvailable::code($code);
        }

        $tenant->forceFill(['plan_id' => $plan->getKey()])->save();

        return $tenant;
    }
}
