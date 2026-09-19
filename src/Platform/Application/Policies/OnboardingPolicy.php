<?php

declare(strict_types=1);

namespace Ronda\Platform\Application\Policies;

use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\PermissionName;

/**
 * Quien pone en marcha la cuenta. RONDA-PLAN-MAESTRO.md sec. 15.3
 *
 * No cuelga de un modelo: la puesta en marcha no es un registro, es el estado
 * de la cuenta entera. Se registra como habilidad suelta
 * (`complete-onboarding`) en AuthorizationServiceProvider, pero la decision
 * vive aqui porque los permisos solo se comprueban en Policies (regla 4).
 *
 * `site.manage` es el permiso: quien puede crear sedes es quien esta montando
 * la operacion. A un encargado de local el asistente no le sirve de nada —no
 * puede hacer ninguno de los pasos— y verlo seria pedirle algo que no puede.
 */
final readonly class OnboardingPolicy
{
    public function complete(User $actor): bool
    {
        return $actor->hasPermissionTo(PermissionName::SiteManage->value);
    }
}
