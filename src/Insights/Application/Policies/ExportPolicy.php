<?php

declare(strict_types=1);

namespace Ronda\Insights\Application\Policies;

use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\PermissionName;
use Ronda\Insights\Domain\Models\Export;

/**
 * Quien puede pedir y descargar exportaciones.
 * RONDA-PLAN-MAESTRO.md sec. 10.3 y 13
 *
 * Pedirla exige `report.view`, igual que el tablero: una exportacion es el
 * mismo dato en otro formato.
 *
 * Descargarla es SOLO de quien la pidio. El archivo ya esta hecho: sus filas se
 * calcularon con las sedes que esa persona alcanzaba en ese momento, asi que
 * dejarlo abierto a cualquiera con `report.view` serviria datos de sedes que
 * quien descarga no puede ver. Si otro lo necesita, lo pide.
 */
final readonly class ExportPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermissionTo(PermissionName::ReportView->value);
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function view(User $actor, Export $export): bool
    {
        return $export->requested_by === $actor->getKey();
    }

    public function download(User $actor, Export $export): bool
    {
        return $this->view($actor, $export) && $export->status->isDownloadable();
    }
}
