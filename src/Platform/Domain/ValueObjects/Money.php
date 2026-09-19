<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Un importe con su moneda. RONDA-PLAN-MAESTRO.md sec. 8.1
 *
 * El monto es una CADENA decimal y las cuentas se hacen con bcmath: en coma
 * flotante, 0.1 + 0.2 no es 0.3, y una factura que no cuadra por un centimo es
 * una factura que el cliente no paga hasta que alguien la explique.
 *
 * La moneda viaja pegada al monto para que no se pueda sumar soles con
 * dolares por descuido: el dia que haya clientes en USD, eso ocurriria solo.
 */
final readonly class Money
{
    /** @var numeric-string */
    public string $amount;

    public string $currency;

    public function __construct(string $amount, string $currency)
    {
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException("«{$amount}» no es un importe.");
        }

        if (strlen($currency) !== 3) {
            throw new InvalidArgumentException("«{$currency}» no es un codigo de moneda ISO.");
        }

        $this->amount = bcadd($amount, '0', 2);
        $this->currency = mb_strtoupper($currency);
    }

    public static function zero(string $currency): self
    {
        return new self('0', $currency);
    }

    public function times(int $factor): self
    {
        return new self(bcmul($this->amount, (string) $factor, 2), $this->currency);
    }

    public function plus(self $other): self
    {
        $this->guardSameCurrency($other);

        return new self(bcadd($this->amount, $other->amount, 2), $this->currency);
    }

    public function isZero(): bool
    {
        return bccomp($this->amount, '0', 2) === 0;
    }

    public function isPositive(): bool
    {
        return bccomp($this->amount, '0', 2) === 1;
    }

    /**
     * En centimos enteros, que es como lo quieren las pasarelas: Culqi rechaza
     * decimales, y convertir con un float es justo donde se pierde el centavo.
     */
    public function cents(): int
    {
        return (int) bcmul($this->amount, '100', 0);
    }

    public function format(): string
    {
        return $this->currency.' '.$this->amount;
    }

    private function guardSameCurrency(self $other): void
    {
        if ($other->currency !== $this->currency) {
            throw new InvalidArgumentException(
                "No se pueden sumar {$this->currency} y {$other->currency}.",
            );
        }
    }
}
