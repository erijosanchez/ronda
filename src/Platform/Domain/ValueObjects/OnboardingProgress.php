<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Cuanto le falta a una cuenta para estar en marcha.
 * RONDA-PLAN-MAESTRO.md sec. 15.3
 *
 * Exige los cinco pasos y no completa los que falten: un paso ausente seria un
 * paso dado por no hecho sin que nadie lo haya mirado, y la pantalla mostraria
 * pendiente algo que quiza esta resuelto.
 */
final readonly class OnboardingProgress
{
    /** @var array<string, bool> */
    private array $steps;

    /**
     * @param  array<string, bool>  $steps  Indexado por el valor de OnboardingStep.
     */
    public function __construct(array $steps)
    {
        $completo = [];

        foreach (OnboardingStep::cases() as $paso) {
            if (! array_key_exists($paso->value, $steps)) {
                throw new InvalidArgumentException("Falta el paso «{$paso->value}».");
            }

            $completo[$paso->value] = $steps[$paso->value];
        }

        $this->steps = $completo;
    }

    public function isDone(OnboardingStep $step): bool
    {
        return $this->steps[$step->value];
    }

    /**
     * El primero que falta, que es el unico que hay que proponer. Proponer
     * cinco cosas a la vez es no proponer ninguna.
     */
    public function next(): ?OnboardingStep
    {
        foreach (OnboardingStep::cases() as $paso) {
            if (! $this->steps[$paso->value]) {
                return $paso;
            }
        }

        return null;
    }

    public function finished(): bool
    {
        return ! $this->next() instanceof OnboardingStep;
    }

    public function done(): int
    {
        return count(array_filter($this->steps));
    }

    public function total(): int
    {
        return count($this->steps);
    }

    public function percentage(): int
    {
        return (int) round($this->done() / $this->total() * 100);
    }
}
