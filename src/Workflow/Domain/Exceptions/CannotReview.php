<?php

declare(strict_types=1);

namespace Ronda\Workflow\Domain\Exceptions;

use DomainException;

/**
 * Una accion de revision que no se puede hacer sobre este envio, o no por esta
 * persona.
 *
 * Lo que decide aqui no son permisos (eso es la Policy) sino reglas del flujo:
 * quien puede tener el permiso de aprobar y aun asi no debe aprobar ESTE envio.
 */
final class CannotReview extends DomainException
{
    public static function ownSubmission(): self
    {
        return new self('No puedes revisar un envio que entregaste tu.');
    }

    public static function takenByOther(string $reviewer): self
    {
        return new self("Este envio ya lo esta revisando {$reviewer}.");
    }

    public static function notReviewable(string $state): self
    {
        return new self("Un envio en estado «{$state}» no se puede revisar.");
    }

    public static function commentRequired(): self
    {
        return new self('Para rechazar hay que explicar que se debe corregir.');
    }

    public static function notCorrectable(string $state): self
    {
        return new self("Solo se corrige un envio rechazado; este esta en «{$state}».");
    }

    public static function emptyComment(): self
    {
        return new self('El comentario esta vacio.');
    }
}
