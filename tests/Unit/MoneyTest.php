<?php

declare(strict_types=1);

use Ronda\Platform\Domain\ValueObjects\Money;

// Dinero. RONDA-PLAN-MAESTRO.md sec. 8.1
//
// Lo que se prueba es lo que un float haria mal: multiplicar una tarifa por un
// numero de sedes sin perder centimos, y no dejar sumar monedas distintas.

it('multiplica sin perder centimos', function (): void {
    // 29.99 x 7 son 209.93 exactos. En coma flotante sale 209.92999999999998.
    $tarifa = new Money('29.99', 'PEN');

    expect($tarifa->times(7)->amount)->toBe('209.93')
        ->and($tarifa->times(7)->cents())->toBe(20993);
});

it('normaliza a dos decimales y a moneda en mayusculas', function (): void {
    $importe = new Money('29', 'pen');

    expect($importe->amount)->toBe('29.00')
        ->and($importe->currency)->toBe('PEN')
        ->and($importe->format())->toBe('PEN 29.00');
});

it('suma importes de la misma moneda', function (): void {
    $total = new Money('10.10', 'PEN')->plus(new Money('0.20', 'PEN'));

    // 10.10 + 0.20 en float da 10.299999999999999.
    expect($total->amount)->toBe('10.30');
});

it('no deja sumar soles con dolares', function (): void {
    expect(fn (): Money => new Money('10', 'PEN')->plus(new Money('10', 'USD')))
        ->toThrow(InvalidArgumentException::class);
});

it('rechaza lo que no es un importe ni una moneda', function (): void {
    expect(fn (): Money => new Money('gratis', 'PEN'))->toThrow(InvalidArgumentException::class)
        ->and(fn (): Money => new Money('10', 'SOLES'))->toThrow(InvalidArgumentException::class);
});
