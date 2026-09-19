<?php

declare(strict_types=1);

namespace Ronda\Platform\Infrastructure\Plans;

use Ronda\Platform\Domain\Contracts\PlanProvider;
use Ronda\Platform\Domain\Models\Plan;
use Ronda\Platform\Domain\Models\Tenant;
use Ronda\Platform\Domain\PlanFeature;
use Ronda\Platform\Domain\ValueObjects\PlanLimits;

/**
 * El plan del cliente en curso, leido de la base central.
 * RONDA-PLAN-MAESTRO.md sec. 3.6
 *
 * Se resuelve una vez por peticion y se recuerda: los limites se preguntan en
 * cada escritura y en la pantalla de uso, y no tiene sentido volver a la base
 * central cada vez dentro de la misma peticion. Mas alla de la peticion no se
 * guarda nada: un cambio de plan tiene que notarse en la siguiente.
 *
 * Sin tenant (consola, jobs centrales) o sin plan contratado (cliente en
 * prueba) no se corta nada: quien decide que pasa cuando termina la prueba es
 * la facturacion, no los limites.
 */
final class CurrentTenantPlan implements PlanProvider
{
    private ?Plan $resolved = null;

    private bool $lookedUp = false;

    public function limits(): PlanLimits
    {
        return $this->current()?->limits() ?? PlanLimits::unlimited();
    }

    public function allows(PlanFeature $feature): bool
    {
        $plan = $this->current();

        // Sin plan, todo abierto: es la prueba, y es donde el cliente tiene
        // que ver lo que estaria comprando.
        return ! $plan instanceof Plan || $plan->includes($feature);
    }

    public function current(): ?Plan
    {
        if ($this->lookedUp) {
            return $this->resolved;
        }

        $this->lookedUp = true;

        $tenant = tenant();

        if (! $tenant instanceof Tenant || $tenant->plan_id === null) {
            return $this->resolved = null;
        }

        return $this->resolved = Plan::query()->find($tenant->plan_id);
    }
}
