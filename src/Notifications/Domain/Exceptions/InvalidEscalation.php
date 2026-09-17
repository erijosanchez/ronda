<?php

declare(strict_types=1);

namespace Ronda\Notifications\Domain\Exceptions;

use DomainException;

/**
 * Una escalera de escalamiento mal configurada. Se descubre al arrancar el
 * repaso, no cuando haria falta avisar.
 */
final class InvalidEscalation extends DomainException
{
    public static function unknownAudience(string $audience): self
    {
        return new self("«{$audience}» no es una audiencia conocida para escalar.");
    }

    public static function negativeDelay(int $minutes): self
    {
        return new self("El retraso de un peldano no puede ser negativo; se recibio {$minutes}.");
    }
}
