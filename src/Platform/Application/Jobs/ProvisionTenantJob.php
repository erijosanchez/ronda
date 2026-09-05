<?php

declare(strict_types=1);

namespace Ronda\Platform\Application\Jobs;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Events\Dispatcher as Events;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\RoleName;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Events\TenantProvisioned;
use Ronda\Platform\Domain\Models\Tenant;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;
use Stancl\Tenancy\Jobs\SeedDatabase;

/**
 * Deja un tenant listo para entrar. RONDA-PLAN-MAESTRO.md sec. 7.2
 *
 *   CREATE DATABASE  ->  migrate  ->  seed  ->  propietario  ->  evento
 *
 * Reutiliza los jobs de stancl/tenancy en vez de reimplementarlos: ellos
 * emiten los eventos del paquete (CreatingDatabase, DatabaseCreated...) y
 * respetan la bandera interna `create_database`, que es como se dan de alta
 * tenants que apuntan a una base ya existente.
 *
 * Los pasos que faltan (plantillas del plan, prefijo de evidencia en el bucket)
 * llegan con sus modulos; el evento TenantProvisioned es el enganche.
 */
final class ProvisionTenantJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly CreateTenantData $data,
    ) {}

    public function handle(Dispatcher $bus, Events $events): void
    {
        // Sincronos y en orden: migrar antes de que exista la base, o sembrar
        // antes de migrar, deja el tenant en un estado que nadie sabe reparar.
        $bus->dispatchSync(new CreateDatabase($this->tenant));
        $bus->dispatchSync(new MigrateDatabase($this->tenant));
        $bus->dispatchSync(new SeedDatabase($this->tenant));

        $this->createOwner();

        $events->dispatch(new TenantProvisioned($this->tenant));
    }

    /**
     * El propietario se crea dentro del contexto del tenant: `run()` conmuta
     * la conexion, ejecuta y la devuelve al estado anterior aunque lance.
     */
    private function createOwner(): void
    {
        $this->tenant->run(function (): void {
            $owner = User::create([
                'name' => $this->data->ownerName,
                'email' => $this->data->ownerEmail,
                // El cast `hashed` del modelo aplica Argon2id (sec. 10.2).
                'password' => $this->data->ownerPassword,
            ]);

            $owner->assignRole(RoleName::Owner->value);
        });
    }
}
