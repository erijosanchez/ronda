<?php

declare(strict_types=1);

namespace Ronda\Directory\Domain\Exceptions;

use DomainException;

/**
 * Una zona que no se puede borrar porque algo cuelga de ella.
 *
 * Se comprueba aqui y no se deja caer en la restriccion de la base: el error de
 * PostgreSQL es una pantalla en blanco, y esto es un mensaje que dice que
 * mover primero.
 */
final class CannotDeleteZone extends DomainException
{
    public static function hasSites(int $sites): self
    {
        return new self("La zona todavia tiene {$sites} sede(s). Muevelas a otra zona antes de borrarla.");
    }

    public static function hasChildren(int $children): self
    {
        return new self("La zona todavia tiene {$children} zona(s) dentro. Muevelas o borralas antes.");
    }

    public static function hasSchedules(int $schedules): self
    {
        return new self("La zona se usa en {$schedules} programacion(es). Cambialas de alcance antes de borrarla.");
    }

    public static function ownParent(): self
    {
        return new self('Una zona no puede colgar de si misma.');
    }

    public static function parentInOwnBranch(): self
    {
        return new self('Una zona no puede colgar de una de sus propias zonas hijas.');
    }
}
