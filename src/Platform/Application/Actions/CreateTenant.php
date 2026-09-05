<?php

declare(strict_types=1);

namespace Ronda\Platform\Application\Actions;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Application\Jobs\ProvisionTenantJob;
use Ronda\Platform\Domain\Models\Tenant;

/**
 * Da de alta un cliente en la base central y encola su provision.
 * RONDA-PLAN-MAESTRO.md sec. 7.2
 *
 * Escribe en dos tablas (`tenants` y `domains`), asi que va en transaccion.
 * La provision NO puede ir dentro: PostgreSQL prohibe `CREATE DATABASE` dentro
 * de un bloque de transaccion, y ademas una base a medias por un rollback
 * dejaria basura en el servidor. Por eso se despacha despues del commit.
 *
 * Se inyecta ConnectionInterface en vez de usar la facade DB porque la capa de
 * aplicacion no puede depender de facades (test de arquitectura).
 */
final readonly class CreateTenant
{
    public function __construct(
        private ConnectionInterface $connection,
        private Dispatcher $dispatcher,
    ) {}

    public function __invoke(CreateTenantData $data): Tenant
    {
        $tenant = $this->connection->transaction(function () use ($data): Tenant {
            $tenant = new Tenant;

            // El ULID se fija aqui, no en el modelo: el identificador del
            // cliente es una decision de negocio, no un detalle del ORM.
            $tenant->id = (string) Str::ulid();
            $tenant->name = $data->name;
            $tenant->slug = $data->slug;
            // El estado inicial no se fija aqui: lo pone TenantStatus::config()
            // con ->default(Trial::class). Escribirlo tambien en la Action daria
            // dos sitios que decir cual es el estado de alta.
            $tenant->save();

            $tenant->domains()->create(['domain' => $data->domain]);

            return $tenant;
        });

        $this->dispatcher->dispatch(new ProvisionTenantJob($tenant, $data));

        return $tenant;
    }
}
