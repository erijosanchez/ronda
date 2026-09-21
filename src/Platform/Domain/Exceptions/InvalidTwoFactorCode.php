<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Exceptions;

use DomainException;

/**
 * El codigo del segundo factor no vale.
 *
 * El mensaje es el mismo se haya equivocado de digito o este probando codigos:
 * decirle a quien prueba «ese casi» es ayudarle.
 */
final class InvalidTwoFactorCode extends DomainException
{
    public static function forEnrollment(): self
    {
        return new self('The confirmation code is not valid.');
    }

    public static function forChallenge(): self
    {
        return new self('The code is not valid.');
    }
}
