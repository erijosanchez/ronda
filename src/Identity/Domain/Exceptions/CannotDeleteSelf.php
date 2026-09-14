<?php

declare(strict_types=1);

namespace Ronda\Identity\Domain\Exceptions;

use DomainException;

/**
 * Nadie se borra a si mismo.
 *
 * UserPolicy ya lo impide, pero `Gate::before` deja pasar al propietario sin
 * consultarla. Se repite aqui para que el invariante se cumpla siempre, venga
 * la llamada de donde venga.
 */
final class CannotDeleteSelf extends DomainException
{
    public static function make(): self
    {
        return new self('No puedes eliminar tu propia cuenta.');
    }
}
