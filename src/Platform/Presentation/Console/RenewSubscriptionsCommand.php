<?php

declare(strict_types=1);

namespace Ronda\Platform\Presentation\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Platform\Application\Actions\ChargeInvoice;
use Ronda\Platform\Application\Actions\IssueInvoice;
use Ronda\Platform\Domain\Billing\InvoiceStatus;
use Ronda\Platform\Domain\Billing\SubscriptionStatus;
use Ronda\Platform\Domain\Exceptions\BillingGatewayFailed;
use Ronda\Platform\Domain\Models\Invoice;
use Ronda\Platform\Domain\Models\Subscription;
use Ronda\Platform\Domain\Models\Tenant;

/**
 * Emite y cobra lo que toca hoy. RONDA-PLAN-MAESTRO.md sec. 15.1
 *
 * Dos pasadas, en este orden:
 *
 *   1. Periodos vencidos: se emite la factura del periodo con las sedes activas
 *      de ESE cliente, contadas dentro de su base.
 *   2. Cobros rechazados cuyo reintento ya toca.
 *
 * Emitir y cobrar van juntos a proposito: una factura emitida y no cobrada es
 * justo lo que hay que poder ver, y separarlo en dos comandos haria que un
 * fallo en el segundo pasara desapercibido.
 *
 * Todo es idempotente: la factura de un periodo tiene clave unica en la base,
 * asi que correr esto dos veces el mismo dia no cobra dos veces. Con
 * `BILLING_GATEWAY=manual` emite igual y no cobra nada: las facturas quedan
 * pendientes de conciliar.
 */
final class RenewSubscriptionsCommand extends Command
{
    protected $signature = 'billing:renew {--dry-run : Mostrar que se emitiria y cobraria, sin tocar nada}';

    protected $description = 'Emite el cobro de los periodos vencidos y reintenta los rechazados';

    public function handle(IssueInvoice $issue, ChargeInvoice $charge): int
    {
        $ahora = CarbonImmutable::now('UTC');
        $seco = (bool) $this->option('dry-run');

        $emitidas = $this->issueDuePeriods($issue, $ahora, $seco);
        $cobradas = $this->chargePending($charge, $ahora, $seco);

        $this->info("Periodos emitidos: {$emitidas}. Cobros intentados: {$cobradas}.");

        return self::SUCCESS;
    }

    private function issueDuePeriods(IssueInvoice $issue, CarbonImmutable $now, bool $dryRun): int
    {
        $emitidas = 0;

        Subscription::query()
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::PastDue->value])
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', $now)
            ->with('tenant')
            ->each(function (Subscription $suscripcion) use ($issue, $now, $dryRun, &$emitidas): void {
                $tenant = $suscripcion->tenant;

                if (! $tenant instanceof Tenant) {
                    return;
                }

                $sedes = $this->activeSites($tenant);

                if ($dryRun) {
                    $this->line("  {$tenant->slug}: {$sedes} sede(s) · ".$suscripcion->amountFor($sedes)->format());

                    return;
                }

                $issue($suscripcion, $sedes, $now);
                $emitidas++;
            });

        return $emitidas;
    }

    private function chargePending(ChargeInvoice $charge, CarbonImmutable $now, bool $dryRun): int
    {
        $intentados = 0;

        Invoice::query()
            ->where(function ($query) use ($now): void {
                $query->where('status', InvoiceStatus::Pending->value)
                    ->orWhere(function ($fallidas) use ($now): void {
                        $fallidas->where('status', InvoiceStatus::Failed->value)
                            ->whereNotNull('retry_after')
                            ->where('retry_after', '<=', $now);
                    });
            })
            ->with(['subscription', 'tenant'])
            ->each(function (Invoice $factura) use ($charge, $now, $dryRun, &$intentados): void {
                $suscripcion = $factura->subscription;

                // Sin tarjeta no hay nada que intentar: se cobra por fuera.
                if (! $suscripcion instanceof Subscription || ! $suscripcion->canBeCharged()) {
                    return;
                }

                if ($dryRun) {
                    $this->line('  cobro '.$factura->money()->format()." (factura {$factura->id})");

                    return;
                }

                try {
                    $charge($factura, $now);
                } catch (BillingGatewayFailed $e) {
                    // La pasarela caida no es un impago del cliente: se anota y
                    // se sigue con los demas. La factura queda como estaba y el
                    // proximo pase vuelve a intentarlo.
                    $this->error("  factura {$factura->id}: ".$e->getMessage());
                    report($e);
                }

                $intentados++;
            });

        return $intentados;
    }

    /**
     * Las sedes se cuentan DENTRO del cliente: es lo que se factura, y vive en
     * su base. Sin los scopes de frontera, que aqui no hay sesion que filtrar.
     */
    private function activeSites(Tenant $tenant): int
    {
        return (int) $tenant->run(
            static fn (): int => Site::query()->withoutGlobalScopes()->count(),
        );
    }
}
