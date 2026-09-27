<?php

declare(strict_types=1);

namespace Ronda\Api\Application\Policies;

use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\PermissionName;

/**
 * Quien puede emitir tokens de la API. RONDA-PLAN-MAESTRO.md sec. 13.1
 *
 * No cuelga de un modelo: se registra como habilidad suelta (`manage-api`) y
 * la decision vive aqui, porque los permisos solo se comprueban en Policies
 * (regla 4).
 *
 * `user.manage` es el permiso: un token puede leer lo que lee su dueno, asi
 * que entregarlo equivale a delegar su acceso. Eso lo decide quien administra
 * a las personas, no quien entrega reportes.
 */
final readonly class ApiTokenPolicy
{
    public function manage(User $actor): bool
    {
        return $actor->hasPermissionTo(PermissionName::UserManage->value);
    }
}
