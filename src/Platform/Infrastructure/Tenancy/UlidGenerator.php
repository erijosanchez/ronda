<?php

declare(strict_types=1);

namespace Ronda\Platform\Infrastructure\Tenancy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Stancl\Tenancy\Contracts\UniqueIdentifierGenerator;

/**
 * Identificadores de tenant en ULID en vez de UUIDv4.
 *
 * Ordenan por tiempo de creacion, asi que el indice primario de `tenants` no
 * se fragmenta y el nombre de la base (`ronda_tnt_<ulid>`) permite reconocer
 * de un vistazo el orden de alta al listar el parque.
 *
 * No se puede quitar del todo: stancl/tenancy decide con
 * `app()->bound(UniqueIdentifierGenerator::class)` si la clave primaria es
 * autoincremental. Sin un generador enlazado, el modelo Tenant se tomaria por
 * autoincremental y romperia la clave de tipo texto.
 */
final class UlidGenerator implements UniqueIdentifierGenerator
{
    /**
     * La firma de la interfaz no lleva tipo y PHP no permite estrecharla al
     * implementarla; el tipo real se declara aqui para el analisis estatico.
     *
     * @param  Model  $resource
     */
    public static function generate($resource): string
    {
        return (string) Str::ulid();
    }
}
