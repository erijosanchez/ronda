<?php

declare(strict_types=1);

namespace Ronda\Platform\Infrastructure\Listeners;

use Carbon\CarbonImmutable;
use Ronda\Platform\Domain\Events\TenantProvisioned;

/**
 * Deja constancia de que un cliente ya se puede usar.
 * RONDA-PLAN-MAESTRO.md sec. 15.3
 *
 * Lo anota en la base CENTRAL: quien pregunta es la pantalla de «preparando tu
 * cuenta», que todavia no puede conectarse a la base del cliente.
 *
 * No va en cola: es una escritura de una fila al final de la provision, y
 * encolarla abriria una ventana en la que el cliente esta listo pero nadie lo
 * sabe.
 */
final readonly class MarkTenantProvisioned
{
    public function handle(TenantProvisioned $event): void
    {
        $event->tenant->forceFill(['provisioned_at' => CarbonImmutable::now('UTC')])->save();
    }
}
