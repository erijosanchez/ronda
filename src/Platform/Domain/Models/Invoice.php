<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ronda\Platform\Domain\Billing\InvoiceStatus;
use Ronda\Platform\Domain\ValueObjects\Money;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Un cobro por un periodo. RONDA-PLAN-MAESTRO.md sec. 15.1
 *
 * Guarda como salio el numero —sedes facturadas, precio por sede y meses— para
 * que una factura se pueda explicar anos despues sin reconstruir el pasado.
 *
 * No es el comprobante de SUNAT: ese lo emite el PSE y se enlaza en
 * `document_url`.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $subscription_id
 * @property InvoiceStatus $status
 * @property string $amount
 * @property string $currency
 * @property int $billed_sites
 * @property string $price_per_site
 * @property int $billed_months
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable $period_end
 * @property CarbonImmutable $issued_at
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $retry_after
 * @property int $attempts
 * @property string|null $failure_reason
 * @property string $gateway
 * @property string|null $external_id
 */
final class Invoice extends Model
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
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function money(): Money
    {
        return new Money($this->amount, $this->currency);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'period_start' => 'immutable_date',
            'period_end' => 'immutable_date',
            'issued_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'retry_after' => 'immutable_datetime',
        ];
    }
}
