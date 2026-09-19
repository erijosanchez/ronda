<?php

declare(strict_types=1);

use Ronda\Platform\Domain\ValueObjects\OnboardingProgress;
use Ronda\Platform\Domain\ValueObjects\OnboardingStep;

// El avance del arranque. RONDA-PLAN-MAESTRO.md sec. 15.3

/**
 * @param  list<OnboardingStep>  $hechos
 */
function avanceCon(array $hechos): OnboardingProgress
{
    $pasos = [];

    foreach (OnboardingStep::cases() as $paso) {
        $pasos[$paso->value] = in_array($paso, $hechos, true);
    }

    return new OnboardingProgress($pasos);
}

it('propone el primer paso pendiente y no los demas', function (): void {
    // Con el primero hecho, lo que toca es el segundo, aunque el cuarto
    // tambien este hecho: el orden lo decide el enum, no lo que falte menos.
    $avance = avanceCon([OnboardingStep::Templates, OnboardingStep::Schedules]);

    expect($avance->next())->toBe(OnboardingStep::Sites)
        ->and($avance->isDone(OnboardingStep::Templates))->toBeTrue()
        ->and($avance->isDone(OnboardingStep::Sites))->toBeFalse()
        ->and($avance->done())->toBe(2)
        ->and($avance->total())->toBe(5)
        ->and($avance->percentage())->toBe(40)
        ->and($avance->finished())->toBeFalse();
});

it('se da por terminado solo con los cinco pasos', function (): void {
    $avance = avanceCon(OnboardingStep::cases());

    expect($avance->finished())->toBeTrue()
        ->and($avance->next())->toBeNull()
        ->and($avance->percentage())->toBe(100);
});

it('no acepta un avance al que le falta un paso', function (): void {
    // Un paso ausente seria un paso dado por no hecho sin que nadie lo mire.
    expect(fn (): OnboardingProgress => new OnboardingProgress([
        OnboardingStep::Templates->value => true,
    ]))->toThrow(InvalidArgumentException::class);
});
