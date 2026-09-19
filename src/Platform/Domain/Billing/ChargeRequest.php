<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Billing;

use Ronda\Platform\Domain\ValueObjects\Money;

/**
 * Un cobro que se le pide a la pasarela.
 *
 * Lleva la referencia interna (`reference`) para que el cobro se pueda
 * reconciliar despues en las dos direcciones: de nuestra factura a su cargo, y
 * de su reporte a nuestra factura. Sin eso, cuadrar un mes es a mano.
 */
final readonly class ChargeRequest
{
    /**
     * @param  string  $source  Lo que identifica la tarjeta ante la pasarela:
     *                          un token de un solo uso o una tarjeta guardada.
     * @param  array<string, string>  $metadata
     */
    public function __construct(
        public Money $amount,
        public string $source,
        public string $email,
        public string $description,
        public string $reference,
        public array $metadata = [],
    ) {}
}
