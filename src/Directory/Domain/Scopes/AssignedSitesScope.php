<?php

declare(strict_types=1);

namespace Ronda\Directory\Domain\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Ronda\Identity\Domain\Models\User;

/**
 * Frontera por sede. RONDA-PLAN-MAESTRO.md sec. 10.3
 *
 * Cada usuario ve solo las sedes que tiene asignadas en `user_site`. Es la
 * PRIMERA de las dos capas que exige el plan; la segunda es SitePolicy. Se
 * duplica a proposito: un scope global se puede desactivar sin querer con un
 * `withoutGlobalScopes()` puesto para otra cosa, y entonces la Policy sigue
 * ahi.
 *
 * Quien puede ver todo el parque NO se decide aqui. El scope pregunta por la
 * habilidad `viewAll` y es SitePolicy quien responde, que es donde el proyecto
 * concentra la autorizacion: un `hasRole()` suelto en un scope seria justo lo
 * que prohibe la regla 4.
 *
 * Sin sesion no filtra: en consola, en jobs y en los seeders hace falta ver el
 * parque entero, y ninguno de esos caminos expone datos a un usuario. El que
 * si lo hace, el HTTP, siempre tiene sesion.
 */
final class AssignedSitesScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        if ($user->can('viewAll', $model::class)) {
            return;
        }

        $builder->whereIn(
            $model->getQualifiedKeyName(),
            fn ($query) => $query
                ->select('site_id')
                ->from('user_site')
                ->where('user_id', $user->getKey()),
        );
    }
}
