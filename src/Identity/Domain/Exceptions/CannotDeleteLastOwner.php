<?php

declare(strict_types=1);

namespace Ronda\Identity\Domain\Exceptions;

use DomainException;

/**
 * Un cliente no puede quedarse sin propietario.
 * RONDA-PLAN-MAESTRO.md sec. 10.3
 *
 * Es un invariante de NEGOCIO, no de autorizacion, y por eso vive en la Action
 * y no en la Policy: `Gate::before` concede todo al propietario antes de que
 * ninguna Policy llegue a ejecutarse, asi que alli no habria forma de frenarlo.
 */
final class CannotDeleteLastOwner extends DomainException
{
    public static function make(): self
    {
        return new self('No se puede eliminar al ultimo propietario del cliente.');
    }
}
