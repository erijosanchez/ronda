<?php

declare(strict_types=1);

namespace Ronda\Platform\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Ronda\Platform\Domain\Billing\ChargeRequest;
use Ronda\Platform\Domain\Billing\InvoiceStatus;
use Ronda\Platform\Domain\Billing\SubscriptionStatus;
use Ronda\Platform\Domain\Contracts\BillingGateway;
use Ronda\Platform\Domain\Models\Invoice;
use Ronda\Platform\Domain\Models\Subscription;
use Ronda\Platform\Domain\States\Active;
use Ronda\Platform\Domain\States\PastDue;

/**
 * Cobra una factura pendiente. RONDA-PLAN-MAESTRO.md sec. 15.1 y 15.2
 *
 * Tres tablas se tocan —factura, suscripcion y estado del cliente—, asi que va
 * en transaccion (regla 3).
 *
 * Lo que decide el resultado:
 *
 *   - Sin tarjeta guardada, la factura se queda PENDIENTE. No es un impago: es
 *     un cobro por transferencia esperando conciliacion, y marcar moroso a
 *     quien pago por otro medio es como se pierde un cliente que estaba al dia.
 *   - Cobrada: factura pagada, cliente activo, siguiente periodo abierto.
 *   - Rechazada: se anota el motivo y se programa el reintento
 *     (`billing.retry_days`). Solo cuando se agotan los reintentos el cliente
 *     pasa a `past_due`, que es el estado que despues permite suspender.
 *
 * Una tarjeta rechazada no es una excepcion: casi siempre es un limite del dia
 * o una tarjeta vencida, y el cliente sigue siendo cliente.
 */
final readonly class ChargeInvoice
{
    public function __construct(
        private ConnectionInterface $connection,
        private BillingGateway $gateway,
    ) {}

    public function __invoke(Invoice $invoice, ?CarbonImmutable $now = null): Invoice
    {
        $ahora = $now ?? CarbonImmutable::now('UTC');

        if ($invoice->status !== InvoiceStatus::Pending && $invoice->status !== InvoiceStatus::Failed) {
            return $invoice;
        }

        $suscripcion = $invoice->subscription;

        if (! $suscripcion instanceof Subscription || ! $suscripcion->canBeCharged()) {
            return $invoice;
        }

        $resultado = $this->gateway->charge(new ChargeRequest(
            amount: $invoice->money(),
            source: (string) $suscripcion->card_reference,
            email: $this->billingEmail($invoice),
            description: __('Ronda · :period', ['period' => $invoice->period_start->format('m/Y')]),
            reference: 'invoice-'.$invoice->getKey(),
            metadata: ['tenant' => (string) $invoice->tenant_id],
        ));

        return $this->connection->transaction(function () use ($invoice, $suscripcion, $resultado, $ahora): Invoice {
            $intentos = $invoice->attempts + 1;

            if ($resultado->successful) {
                $invoice->forceFill([
                    'status' => InvoiceStatus::Paid,
                    'paid_at' => $ahora,
                    'attempts' => $intentos,
                    'retry_after' => null,
                    'failure_reason' => null,
                    'external_id' => $resultado->reference,
                ])->save();

                $suscripcion->forceFill([
                    'status' => SubscriptionStatus::Active,
                    // El periodo siguiente arranca donde termino este, no
                    // «hoy»: si el cobro se retraso tres dias, el cliente no
                    // pierde tres dias de servicio.
                    'current_period_start' => $invoice->period_end,
                    'current_period_end' => $invoice->period_end->addMonths($suscripcion->cycle->months()),
                ])->save();

                $this->markTenant($invoice, Active::class);

                return $invoice->refresh();
            }

            $reintentos = $this->retryDays();
            $siguiente = $reintentos[$intentos - 1] ?? null;

            $invoice->forceFill([
                'status' => InvoiceStatus::Failed,
                'attempts' => $intentos,
                'failure_reason' => $resultado->declineReason,
                'retry_after' => $siguiente === null ? null : $ahora->addDays($siguiente),
            ])->save();

            $suscripcion->forceFill(['status' => SubscriptionStatus::PastDue])->save();

            // Solo cuando ya no queda reintento el cliente pasa a `past_due`:
            // hasta entonces sigue operando como si nada, porque casi siempre
            // el segundo intento entra.
            if ($siguiente === null) {
                $this->markTenant($invoice, PastDue::class);
            }

            return $invoice->refresh();
        });
    }

    /**
     * @param  class-string  $state
     */
    private function markTenant(Invoice $invoice, string $state): void
    {
        $tenant = $invoice->tenant;

        if ($tenant === null || $state === $tenant->status::class) {
            return;
        }

        // Puede no haber transicion legal (un cliente archivado, por ejemplo):
        // el cobro no es quien para forzar el ciclo de vida del cliente.
        if ($tenant->status->canTransitionTo($state)) {
            $tenant->status->transitionTo($state);
        }
    }

    /**
     * @return list<int>
     */
    private function retryDays(): array
    {
        $dias = config('billing.retry_days', [3, 7]);

        return is_array($dias) ? array_values(array_map(intval(...), $dias)) : [3, 7];
    }

    /**
     * A donde se avisa del cobro: el correo de quien creo la cuenta.
     * Cuando haya datos de facturacion propios (sec. 8.2), saldra de ahi.
     */
    private function billingEmail(Invoice $invoice): string
    {
        $datos = $invoice->tenant?->data;
        $correo = is_array($datos) ? ($datos['billing_email'] ?? null) : null;

        return is_string($correo) ? $correo : 'facturacion@ronda.pe';
    }
}
