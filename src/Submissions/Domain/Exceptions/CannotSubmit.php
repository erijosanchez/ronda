<?php

declare(strict_types=1);

namespace Ronda\Submissions\Domain\Exceptions;

use DomainException;

/**
 * La obligacion no admite un envio ahora.
 */
final class CannotSubmit extends DomainException
{
    public static function notPending(string $status): self
    {
        return new self("Esta entrega ya no esta pendiente (estado: {$status}).");
    }

    public static function notOpenYet(string $opensAt): self
    {
        return new self("Esta entrega todavia no esta abierta. Abre a las {$opensAt}.");
    }

    public static function closed(): self
    {
        return new self('El plazo de esta entrega ya cerro.');
    }

    public static function templateWithoutVersion(): self
    {
        return new self('La plantilla no tiene ninguna version publicada.');
    }
}
