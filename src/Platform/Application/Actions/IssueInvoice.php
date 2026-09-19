<?php

declare(strict_types=1);

namespace Ronda\Platform\Application\Actions;

use Carbon\CarbonImmutable;
use Ronda\Platform\Domain\Billing\InvoiceStatus;
use Ronda\Platform\Domain\Models\Invoice;
use Ronda\Platform\Domain\Models\Subscription;

/**
 * Emite el cobro de un periodo. RONDA-PLAN-MAESTRO.md sec. 15.1
 *
 * Guarda el detalle de como sale el numero —sedes facturadas, precio por sede y
 * meses cobrados— para que la factura se pueda explicar despues sin
 * reconstruir el pasado.
 *
 * Es IDEMPOTENTE por `(suscripcion, inicio de periodo)`, y no por confianza: la
 * tabla tiene esa clave unica, asi que dos renovaciones a la vez no pueden
 * cobrar dos veces el mismo mes. La segunda encuentra la primera y la devuelve.
 *
 * Las sedes activas se le pasan: esta Action vive en la central y las sedes
 * estan en la base del cliente. Quien las cuenta es quien puede entrar ahi.
 */
final readonly class IssueInvoice
{
    public function __invoke(
        Subscription $subscription,
        int $activeSites,
        ?CarbonImmutable $now = null,
    ): Invoice {
        $ahora = $now ?? CarbonImmutable::now('UTC');

        $inicio = $subscription->current_period_start ?? $ahora;
        $fin = $subscription->current_period_end ?? $inicio->addMonths($subscription->cycle->months());

        $importe = $subscription->amountFor($activeSites);

        return Invoice::query()->firstOrCreate(
            [
                'subscription_id' => $subscription->getKey(),
                'period_start' => $inicio->toDateString(),
            ],
            [
                'tenant_id' => $subscription->tenant_id,
                'status' => InvoiceStatus::Pending,
                'amount' => $importe->amount,
                'currency' => $importe->currency,
                'billed_sites' => max($activeSites, $subscription->min_sites),
                'price_per_site' => $subscription->price_per_site,
                'billed_months' => $subscription->cycle->billedMonths(),
                'period_end' => $fin->toDateString(),
                'issued_at' => $ahora,
                // Explicito y no por el valor por defecto de la columna: si se
                // deja a la base, el objeto recien creado trae `attempts` a
                // null y el primer intento cuenta mal.
                'attempts' => 0,
                'gateway' => $subscription->gateway,
            ],
        );
    }
}
