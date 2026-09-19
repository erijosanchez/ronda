<?php

declare(strict_types=1);

use Ronda\Platform\Domain\ValueObjects\PlanLimits;

// Los limites de un plan. RONDA-PLAN-MAESTRO.md sec. 3.6
//
// Lo que se prueba aqui es la distincion que sostiene todo lo demas: `null` es
// «sin limite», no «cero».

it('trata el limite nulo como sin limite y no como cero', function (): void {
    $sinLimite = PlanLimits::unlimited();

    expect($sinLimite->allowsAnotherTemplate(0))->toBeTrue()
        ->and($sinLimite->allowsAnotherTemplate(9999))->toBeTrue()
        ->and($sinLimite->allowsMoreStorage(1024 ** 4, 1024 ** 3))->toBeTrue()
        ->and($sinLimite->storageBytesPerSite())->toBeNull();
});

it('deja crear hasta el limite y ni una mas', function (): void {
    $starter = new PlanLimits(maxTemplates: 3);

    expect($starter->allowsAnotherTemplate(2))->toBeTrue()
        // Con tres ya creadas, la cuarta no entra.
        ->and($starter->allowsAnotherTemplate(3))->toBeFalse()
        ->and($starter->allowsAnotherTemplate(4))->toBeFalse();
});

it('compara el espacio en bytes, no en gigas redondeados', function (): void {
    $starter = new PlanLimits(storageGbPerSite: 1);
    $gb = 1024 ** 3;

    // Un archivo que cabe justo.
    expect($starter->allowsMoreStorage($gb - 100, 100))->toBeTrue()
        // Y uno que se pasa por cien bytes: redondeando a GB habria pasado.
        ->and($starter->allowsMoreStorage($gb - 100, 200))->toBeFalse()
        ->and($starter->storageBytesPerSite())->toBe($gb);
});

it('no admite un limite negativo', function (): void {
    expect(fn (): PlanLimits => new PlanLimits(maxTemplates: -1))
        ->toThrow(InvalidArgumentException::class);
});
