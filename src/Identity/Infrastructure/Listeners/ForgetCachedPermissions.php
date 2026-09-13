<?php

declare(strict_types=1);

namespace Ronda\Identity\Infrastructure\Listeners;

use Spatie\Permission\PermissionRegistrar;

/**
 * Vacia la cache de permisos al entrar y al salir del contexto de un tenant.
 * RONDA-PLAN-MAESTRO.md sec. 7.4
 *
 * PermissionRegistrar es un singleton y guarda los permisos cargados en una
 * propiedad de instancia; `loadPermissions()` hace cortocircuito si ya la
 * tiene. Bajo Octane el worker sobrevive entre peticiones, asi que sin esto la
 * peticion de un cliente resuelve permisos con los que cargo otro.
 *
 * Comprobado: sin este escuchador, un permiso creado solo en la base del
 * tenant A aparecia al consultar el registrar dentro del tenant B.
 *
 * El prefijo de cache por tenant no basta: el problema no esta en el almacen
 * de cache sino en la coleccion que el registrar guarda en memoria.
 */
final readonly class ForgetCachedPermissions
{
    public function __construct(
        private PermissionRegistrar $registrar,
    ) {}

    /**
     * Sin parametro de evento: se registra en dos eventos distintos y no mira
     * ninguno. El dispatcher le pasa el evento igual y PHP lo ignora.
     */
    public function handle(): void
    {
        $this->registrar->forgetCachedPermissions();
    }
}
