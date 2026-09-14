<?php

declare(strict_types=1);

namespace Ronda\Forms\Domain\Exceptions;

use DomainException;

/**
 * El esquema de una plantilla no se sostiene.
 *
 * PostgreSQL no valida el contenido de una columna JSONB (ADR 0012), asi que la
 * integridad de la definicion la sostienen los value objects del dominio. Esta
 * excepcion es como se manifiesta.
 *
 * Los mensajes dicen QUE campo falla, no solo que algo falla: un esquema con
 * cuarenta campos y un error sin ubicar es inutilizable.
 */
final class InvalidFormSchema extends DomainException
{
    public static function emptySchema(): self
    {
        return new self('Una plantilla necesita al menos un campo.');
    }

    public static function fieldWithoutKey(int $position): self
    {
        return new self("El campo en la posicion {$position} no tiene clave.");
    }

    public static function duplicateKey(string $key): self
    {
        return new self("La clave «{$key}» esta repetida. Cada campo necesita una propia.");
    }

    public static function unknownType(string $key, string $type): self
    {
        return new self("El campo «{$key}» declara un tipo desconocido: «{$type}».");
    }

    public static function optionsRequired(string $key): self
    {
        return new self("El campo «{$key}» es de seleccion y no declara opciones.");
    }

    public static function optionsNotAllowed(string $key): self
    {
        return new self("El campo «{$key}» no admite opciones por su tipo.");
    }

    public static function cannotBeRequired(string $key): self
    {
        return new self("El campo «{$key}» no lo rellena el usuario, asi que no puede ser obligatorio.");
    }

    public static function cannotBeReportable(string $key): self
    {
        return new self("El campo «{$key}» no tiene un valor escalar que indexar, asi que no puede marcarse como reportable.");
    }

    public static function conditionWithoutField(string $key): self
    {
        return new self("La condicion de visibilidad de «{$key}» no dice de que campo depende.");
    }

    public static function unknownOperator(string $key, string $operator): self
    {
        return new self("La condicion de «{$key}» usa un operador desconocido: «{$operator}».");
    }

    public static function conditionOnMissingField(string $key, string $dependsOn): self
    {
        return new self("El campo «{$key}» depende de «{$dependsOn}», que no existe en la plantilla.");
    }

    public static function conditionOnItself(string $key): self
    {
        return new self("El campo «{$key}» no puede depender de si mismo.");
    }
}
