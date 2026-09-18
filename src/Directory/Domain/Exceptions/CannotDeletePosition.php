<?php

declare(strict_types=1);

namespace Ronda\Directory\Domain\Exceptions;

use DomainException;

/**
 * Un cargo que todavia ocupa alguien.
 */
final class CannotDeletePosition extends DomainException
{
    public static function inUse(int $assignments): self
    {
        return new self("El cargo lo ocupan {$assignments} persona(s) en sus sedes. Cambialas de cargo antes de borrarlo.");
    }
}
