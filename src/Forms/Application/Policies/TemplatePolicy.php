<?php

declare(strict_types=1);

namespace Ronda\Forms\Application\Policies;

use Ronda\Forms\Domain\Models\Template;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\PermissionName;

/**
 * Quien puede ver, disenar y publicar plantillas.
 * RONDA-PLAN-MAESTRO.md sec. 10.3
 *
 * Hay tres permisos distintos a proposito: mirar una plantilla, cambiarla y
 * ponerla en produccion no son la misma responsabilidad. Publicar es lo que
 * empieza a exigir entregas a las sedes, asi que tiene el suyo propio.
 */
final readonly class TemplatePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermissionTo(PermissionName::TemplateView->value);
    }

    public function view(User $actor, Template $template): bool
    {
        return $actor->hasPermissionTo(PermissionName::TemplateView->value);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermissionTo(PermissionName::TemplateManage->value);
    }

    public function update(User $actor, Template $template): bool
    {
        return $actor->hasPermissionTo(PermissionName::TemplateManage->value);
    }

    /**
     * Publicar es el acto que pone la plantilla a producir obligaciones. Por
     * eso pide `template.publish` y no `template.manage`: se puede confiar a
     * alguien el diseno sin confiarle el momento de activarlo.
     *
     * La plantilla es opcional para poder preguntar por la habilidad ANTES de
     * crearla. Sin eso, el disenador creaba la plantilla y solo despues
     * descubria que no podia publicarla, dejando una huerfana sin versiones.
     */
    public function publish(User $actor, ?Template $template = null): bool
    {
        return $actor->hasPermissionTo(PermissionName::TemplatePublish->value);
    }

    public function delete(User $actor, Template $template): bool
    {
        return $actor->hasPermissionTo(PermissionName::TemplateManage->value);
    }
}
