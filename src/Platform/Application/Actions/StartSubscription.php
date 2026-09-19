<?php

declare(strict_types=1);

namespace Ronda\Platform\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Ronda\Platform\Domain\Billing\BillingCycle;
use Ronda\Platform\Domain\Billing\SubscriptionStatus;
use Ronda\Platform\Domain\Exceptions\PlanNotAvailable;
use Ronda\Platform\Domain\Models\Plan;
use Ronda\Platform\Domain\Models\Subscription;
use Ronda\Platform\Domain\Models\Tenant;
use Ronda\Platform\Domain\PlanCode;

/**
 * Pone a un cliente en un plan con su periodo de cobro.
 * RONDA-PLAN-MAESTRO.md sec. 15.1
 *
 * Escribe en `subscriptions` y en `tenants` (el plan vigente), asi que va en
 * transaccion (regla 3).
 *
 * El precio y el minimo de sedes se COPIAN del plan. Leerlos en cada cobro
 * subiria el precio a todos los clientes el dia que se toque la tarifa, sin que
 * nadie lo hubiera decidido por cliente.
 *
 * Reusa la fila si el cliente ya tenia suscripcion: cambiar de plan o volver
 * despues de cancelar no crea una segunda, para que nunca haya que decidir
 * cual de dos es la buena. El historial de lo pagado esta en `invoices`.
 */
final readonly class StartSubscription
{
    public function __construct(
        private ConnectionInterface $connection,
        private ChangeTenantPlan $changeTenantPlan,
    ) {}

    /**
     * @throws PlanNotAvailable
     */
    public function __invoke(
        Tenant $tenant,
        PlanCode $code,
        BillingCycle $cycle,
        ?CarbonImmutable $now = null,
    ): Subscription {
        $ahora = $now ?? CarbonImmutable::now('UTC');

        $plan = Plan::query()->where('code', $code->value)->first();

        if (! $plan instanceof Plan) {
            throw PlanNotAvailable::code($code);
        }

        return $this->connection->transaction(function () use ($tenant, $plan, $cycle, $ahora): Subscription {
            ($this->changeTenantPlan)($tenant, $plan->code() ?? PlanCode::default());

            $anterior = Subscription::query()->where('tenant_id', $tenant->id)->first();

            // La prueba se cuenta desde el alta del CLIENTE, no desde ahora: si
            // llevaba diez dias usando Ronda, le quedan cuatro, no catorce.
            $finDePrueba = $anterior instanceof Subscription
                ? $anterior->trial_ends_at
                : $ahora->addDays((int) config('billing.trial_days', 14));

            $enPrueba = $finDePrueba !== null && $finDePrueba->isFuture();

            $suscripcion = $anterior ?? new Subscription;

            $suscripcion->forceFill([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->getKey(),
                'status' => $enPrueba ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
                'cycle' => $cycle,
                'price_per_site' => $plan->price_per_site,
                'currency' => $plan->currency,
                'min_sites' => $plan->min_sites,
                'trial_ends_at' => $finDePrueba,
                // El primer periodo empieza cuando termina la prueba: durante
                // la prueba no se cobra nada (sec. 3.6).
                'current_period_start' => $enPrueba ? $finDePrueba : $ahora,
                'current_period_end' => ($enPrueba ? $finDePrueba : $ahora)->addMonths($cycle->months()),
                'canceled_at' => null,
                'gateway' => (string) config('billing.gateway', 'manual'),
            ])->save();

            return $suscripcion->refresh();
        });
    }
}
