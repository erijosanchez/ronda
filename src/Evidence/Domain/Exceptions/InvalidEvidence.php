<?php

declare(strict_types=1);

namespace Ronda\Evidence\Domain\Exceptions;

use DomainException;

/**
 * Un archivo que no puede entrar como evidencia.
 */
final class InvalidEvidence extends DomainException
{
    public static function empty(): self
    {
        return new self('El archivo esta vacio.');
    }

    public static function tooLarge(int $kilobytes, int $maxKilobytes): self
    {
        return new self("El archivo pesa {$kilobytes} KB y el maximo es {$maxKilobytes} KB.");
    }

    public static function contentNotAllowed(string $mimeType): self
    {
        return new self("El contenido del archivo ({$mimeType}) no esta permitido para este campo.");
    }

    public static function unreadableImage(): self
    {
        return new self('La imagen esta danada o no se puede leer.');
    }

    public static function invalidSignature(): self
    {
        return new self('La firma no es una imagen valida.');
    }

    public static function invalidCoordinates(): self
    {
        return new self('Las coordenadas estan fuera de rango.');
    }

    public static function outsideTenant(): self
    {
        return new self('La evidencia solo se puede guardar dentro de un cliente.');
    }
}
