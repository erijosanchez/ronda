<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Domain\Exceptions;

use DomainException;

/**
 * La regla de recurrencia de una programacion no se sostiene.
 */
final class InvalidRecurrence extends DomainException
{
    public static function unparseable(string $rule, string $reason): self
    {
        return new self("La regla «{$rule}» no es una RRULE valida: {$reason}");
    }

    public static function subDaily(string $frequency): self
    {
        return new self(
            "La frecuencia «{$frequency}» es menor que un dia. Una obligacion es una entrega por dia de sede: "
            .'para varias en el mismo dia hacen falta varias programaciones.',
        );
    }

    public static function containsStart(): self
    {
        return new self('La regla no puede llevar DTSTART: el inicio lo fija la fecha de inicio de la programacion.');
    }

    public static function noWeekdays(): self
    {
        return new self('Una repeticion semanal necesita al menos un dia de la semana.');
    }

    public static function unknownWeekday(string $day): self
    {
        return new self("«{$day}» no es un dia de la semana.");
    }

    public static function invalidMonthDay(int $day): self
    {
        return new self("El dia del mes debe estar entre 1 y 31, o ser el ultimo; se recibio {$day}.");
    }

    public static function invalidInterval(int $interval): self
    {
        return new self("El intervalo debe ser al menos 1; se recibio {$interval}.");
    }

    public static function windowEndsBeforeStart(string $start, string $end): self
    {
        return new self("La ventana termina ({$end}) antes de empezar ({$start}).");
    }
}
