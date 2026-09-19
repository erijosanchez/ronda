<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Exceptions;

/**
 * Por que no se puede dar un subdominio.
 *
 * Son dos casos y se distinguen porque se explican distinto: uno no se puede
 * pedir nunca, el otro se lo llevo alguien antes.
 */
enum SubdomainRejection: string
{
    case Reserved = 'reserved';
    case Taken = 'taken';
}
