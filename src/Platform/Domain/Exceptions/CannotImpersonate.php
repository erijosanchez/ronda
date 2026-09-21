<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Exceptions;

use DomainException;

/**
 * No se puede entrar a la cuenta de este cliente.
 * RONDA-PLAN-MAESTRO.md sec. 15.4
 *
 * Los tres motivos son distintos y se distinguen a proposito: al equipo hay
 * que decirle que falta, porque los tres tienen arreglo.
 */
final class CannotImpersonate extends DomainException
{
    public static function reasonTooShort(int $minimum): self
    {
        return new self("The reason must be at least {$minimum} characters.");
    }

    public static function tenantNotReady(): self
    {
        return new self('That client is not provisioned yet.');
    }

    public static function userNotFound(): self
    {
        return new self('That user does not exist in the client.');
    }
}
