<?php

declare(strict_types=1);

namespace Ronda\Platform\Application\Actions;

use Carbon\CarbonImmutable;
use Ronda\Platform\Domain\Models\ImpersonationEntry;

/**
 * Cierra una sesion de soporte. RONDA-PLAN-MAESTRO.md sec. 15.4
 *
 * Escribe una sola columna, asi que no abre transaccion (regla 3).
 *
 * Es idempotente: cerrar lo ya cerrado no mueve la hora. El cierre puede
 * llegar por tres caminos —el boton de salir, el vencimiento del plazo, o
 * alguien del equipo cortandola desde el back-office— y los tres acaban aqui.
 */
final readonly class EndImpersonation
{
    public function __invoke(ImpersonationEntry $entry, ?CarbonImmutable $now = null): ImpersonationEntry
    {
        if ($entry->ended_at !== null) {
            return $entry;
        }

        $entry->forceFill(['ended_at' => $now ?? CarbonImmutable::now('UTC')])->save();

        return $entry;
    }
}
