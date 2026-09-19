<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Exceptions;

/**
 * Que limite se alcanzo.
 *
 * Existe para que la pantalla pueda explicar cual, sin leer el texto de la
 * excepcion ni comparar cadenas.
 */
enum PlanLimit: string
{
    case Templates = 'templates';
    case StoragePerSite = 'storage_per_site';
}
