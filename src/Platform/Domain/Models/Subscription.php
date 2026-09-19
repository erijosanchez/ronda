<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ronda\Platform\Domain\Billing\BillingCycle;
use Ronda\Platform\Domain\Billing\SubscriptionStatus;
use Ronda\Platform\Domain\ValueObjects\Money;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Lo que un cliente tiene contratado. RONDA-PLAN-MAESTRO.md sec. 15.1
 *
 * Vive en la base central, como el plan: es un acuerdo entre Ronda y el
 * cliente. El precio esta copiado del plan al contratar y no se vuelve a leer,
 * para que una subida de tarifa no alcance a quien ya firmo.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $plan_id
 * @property SubscriptionStatus $status
 * @property BillingCycle $cycle
 * @property string $price_per_site
 * @property string $currency
 * @property int $min_sites
 * @property CarbonImmutable|null $trial_ends_at
 * @property CarbonImmutable|null $current_period_start
 * @property CarbonImmutable|null $current_period_end
 * @property CarbonImmutable|null $canceled_at
 * @property string $gateway
 * @property string|null $card_reference
 */
final class Subscription extends Model
{
    use CentralConnection;

    protected $guarded = [];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Lo que toca cobrar por un periodo, con el minimo del plan como suelo.
     *
     * El numero de sedes se le pasa: esta clase vive en la central y las sedes
     * estan en la base del cliente. Quien las cuenta es quien puede.
     */
    public function amountFor(int $activeSites): Money
    {
        $facturables = max($activeSites, $this->min_sites);

        return new Money($this->price_per_site, $this->currency)
            ->times($facturables)
            ->times($this->cycle->billedMonths());
    }

    /**
     * Si hay con que cobrar sin pedirle nada a nadie.
     */
    public function canBeCharged(): bool
    {
        return $this->card_reference !== null;
    }

    public function onTrial(): bool
    {
        return $this->status === SubscriptionStatus::Trialing
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'cycle' => BillingCycle::class,
            'trial_ends_at' => 'immutable_datetime',
            'current_period_start' => 'immutable_datetime',
            'current_period_end' => 'immutable_datetime',
            'canceled_at' => 'immutable_datetime',
        ];
    }
}
