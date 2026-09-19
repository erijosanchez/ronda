<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Exceptions;

use DomainException;

/**
 * El subdominio que eligio alguien al registrarse no se le puede dar.
 *
 * El mensaje va en ingles porque es para los registros, no para nadie: lo que
 * lee quien se registro lo arma la pantalla con `__()` a partir de `reason` y
 * `subdomain` (CLAUDE.md, regla 10).
 */
final class SubdomainUnavailable extends DomainException
{
    private function __construct(
        public readonly string $subdomain,
        public readonly SubdomainRejection $reason,
    ) {
        parent::__construct("Subdomain «{$subdomain}» is unavailable: {$reason->value}.");
    }

    public static function reserved(string $subdomain): self
    {
        return new self($subdomain, SubdomainRejection::Reserved);
    }

    public static function alreadyTaken(string $subdomain): self
    {
        return new self($subdomain, SubdomainRejection::Taken);
    }
}
