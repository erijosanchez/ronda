<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Billing;

/**
 * Lo que respondio la pasarela.
 *
 * Un rechazo NO es una excepcion: que una tarjeta no tenga fondos es un
 * resultado normal del negocio y hay que guardarlo, contarlo y reintentarlo.
 * Las excepciones se reservan para lo que no deberia pasar: llaves mal puestas,
 * la pasarela caida, una respuesta que no se entiende.
 */
final readonly class ChargeResult
{
    private function __construct(
        public bool $successful,
        public ?string $reference,
        public ?string $declineReason,
    ) {}

    public static function paid(string $reference): self
    {
        return new self(true, $reference, null);
    }

    public static function declined(string $reason): self
    {
        return new self(false, null, $reason);
    }
}
