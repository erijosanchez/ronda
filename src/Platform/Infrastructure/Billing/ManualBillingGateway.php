<?php

declare(strict_types=1);

namespace Ronda\Platform\Infrastructure\Billing;

use Ronda\Platform\Domain\Billing\ChargeRequest;
use Ronda\Platform\Domain\Billing\ChargeResult;
use Ronda\Platform\Domain\Contracts\BillingGateway;
use Ronda\Platform\Domain\Exceptions\BillingGatewayFailed;

/**
 * Cobro fuera del sistema: transferencia, deposito o efectivo.
 * RONDA-PLAN-MAESTRO.md sec. 15.2
 *
 * Es la pasarela por defecto, y no un doble de pruebas: mientras no haya cuenta
 * de comercio, los cobros se acuerdan por fuera, pero las suscripciones, los
 * periodos y los importes tienen que existir igual. Lo unico que no hace es
 * mover dinero.
 *
 * Por eso no guarda tarjetas ni da ningun cobro por bueno. Las facturas quedan
 * PENDIENTES hasta que alguien concilie la transferencia: darlas por pagadas
 * solas seria inventarse un ingreso, y dejarlas como «rechazadas» marcaria
 * moroso a un cliente que pago a tiempo por otro medio.
 */
final readonly class ManualBillingGateway implements BillingGateway
{
    public function name(): string
    {
        return 'manual';
    }

    public function storeCard(string $token, string $email, string $reference): string
    {
        // Sin pasarela no hay donde guardar una tarjeta. Se dice claro en vez
        // de devolver una referencia falsa que luego haria fallar cada cobro.
        throw BillingGatewayFailed::unexpected(
            $this->name(),
            'no card can be stored without a payment gateway; set BILLING_GATEWAY=culqi',
        );
    }

    public function charge(ChargeRequest $request): ChargeResult
    {
        throw BillingGatewayFailed::unexpected(
            $this->name(),
            'manual billing does not charge; invoices stay pending until reconciled',
        );
    }
}
