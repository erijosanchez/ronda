<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Exceptions;

use DomainException;

/**
 * Se intento escribir un plan que todavia tiene filas malas.
 * RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * No es un error que el usuario deba leer: la pantalla ya le enseno los
 * problemas y no le ofrecio el boton de confirmar. Si esto salta, el fallo
 * esta en el codigo que llamo, y por eso el mensaje es para los registros.
 *
 * Existe porque la comprobacion tiene que vivir en la Action y no solo en la
 * pantalla: la API de manana llamara a la misma Action.
 */
final class InvalidImportPlan extends DomainException
{
    public static function withIssues(int $count): self
    {
        return new self("The import plan still has {$count} issues.");
    }
}
