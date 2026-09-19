<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Exceptions;

use DomainException;
use Ronda\Platform\Domain\PlanCode;

/**
 * Se pidio un plan que no esta en el catalogo.
 *
 * Es un fallo de configuracion, no del cliente: significa que el codigo conoce
 * un plan que la base no tiene cargado, casi siempre porque falto correr
 * PlanSeeder despues de un despliegue.
 */
final class PlanNotAvailable extends DomainException
{
    public static function code(PlanCode $code): self
    {
        return new self("Plan «{$code->value}» is not loaded. Run PlanSeeder.");
    }
}
