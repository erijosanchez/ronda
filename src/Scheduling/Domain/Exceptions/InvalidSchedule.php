<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Domain\Exceptions;

use DomainException;

/**
 * Una programacion no se sostiene.
 */
final class InvalidSchedule extends DomainException
{
    public static function templateNotPublished(): self
    {
        return new self('Solo se puede programar una plantilla publicada.');
    }

    public static function zoneRequired(): self
    {
        return new self('Una programacion por zona necesita una zona.');
    }

    public static function sitesRequired(): self
    {
        return new self('Una programacion por lista de sedes necesita al menos una sede.');
    }

    public static function endsBeforeStart(): self
    {
        return new self('La programacion termina antes de empezar.');
    }

    public static function negativeTolerance(): self
    {
        return new self('La tolerancia no puede ser negativa.');
    }

    public static function templateChanged(): self
    {
        return new self(
            'Una programacion no cambia de plantilla: su historial de cumplimiento es de la plantilla original. '
            .'Para pedir otra plantilla, crea otra programacion.',
        );
    }
}
