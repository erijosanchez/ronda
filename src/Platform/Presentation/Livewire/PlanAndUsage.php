<?php

declare(strict_types=1);

namespace Ronda\Platform\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Ronda\Platform\Application\Queries\PlanUsageQuery;
use Ronda\Platform\Domain\Billing\SubscriptionStatus;
use Ronda\Platform\Domain\Contracts\PlanProvider;
use Ronda\Platform\Domain\Models\Plan;
use Ronda\Platform\Domain\Models\Subscription;
use Ronda\Platform\Domain\PlanFeature;

/**
 * Plan contratado y consumo. RONDA-PLAN-MAESTRO.md sec. 3.6 y 15.1
 *
 * Solo lee. Cambiar de plan llega con la facturacion: ofrecer el boton antes
 * de poder cobrar seria prometer algo que no ocurre.
 *
 * Es la pantalla que evita la conversacion peor del SaaS: «me cortaron sin
 * avisar». Aqui se ve cuanto se lleva usado ANTES de chocar contra el limite.
 */
final class PlanAndUsage extends Component
{
    public function mount(): void
    {
        $this->authorize('view-plan');
    }

    public function render(PlanProvider $planProvider, PlanUsageQuery $usage): View
    {
        $plan = $planProvider->current();
        $limits = $planProvider->limits();
        $consumo = $usage();

        // La suscripcion y los cobros viven en la base CENTRAL, asi que se
        // preguntan por el id del cliente y no por la conexion en curso.
        $suscripcion = Subscription::query()->where('tenant_id', tenant('id'))->first();

        return view('platform::plan', [
            'plan' => $plan,
            'subscription' => $suscripcion,
            'pastDue' => $suscripcion?->status === SubscriptionStatus::PastDue,
            'invoices' => $suscripcion instanceof Subscription
                ? $suscripcion->invoices()->orderByDesc('period_start')->limit(12)->get()
                : collect(),
            'limits' => $limits,
            'usage' => $consumo,
            'monthly' => $plan instanceof Plan ? $plan->monthlyPriceFor($consumo->sites) : null,
            'features' => $this->features($planProvider),
        ]);
    }

    /**
     * Las cuatro banderas con su estado, incluidas las apagadas: saber que
     * existe WhatsApp y que su plan no lo trae es justamente lo que hace que
     * alguien pregunte por Pro.
     *
     * @return list<array{feature: PlanFeature, included: bool}>
     */
    private function features(PlanProvider $planProvider): array
    {
        return array_map(
            static fn (PlanFeature $feature): array => [
                'feature' => $feature,
                'included' => $planProvider->allows($feature),
            ],
            PlanFeature::cases(),
        );
    }
}
