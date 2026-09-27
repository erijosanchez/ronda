<?php

declare(strict_types=1);

namespace Ronda\Api\Domain;

/**
 * Lo que un token de la API puede hacer. RONDA-PLAN-MAESTRO.md sec. 13.1
 *
 * Formato `recurso:verbo`. Son POCOS a proposito: un catalogo de treinta
 * alcances no lo entiende nadie y acaba con todo el mundo pidiendo el
 * comodin.
 *
 * Un alcance NO sustituye a la Policy: dice que puede hacer el TOKEN, no que
 * puede hacer la persona. Un token con `sites:write` de alguien que no
 * administra sedes sigue sin poder crearlas. Se comprueban los dos, y en ese
 * orden, porque el alcance es mas barato de mirar.
 */
enum ApiScope: string
{
    case SitesRead = 'sites:read';
    case SitesWrite = 'sites:write';

    case TemplatesRead = 'templates:read';

    case ObligationsRead = 'obligations:read';

    case SubmissionsRead = 'submissions:read';
    case SubmissionsWrite = 'submissions:write';

    public function label(): string
    {
        return __('api-scopes.'.$this->value);
    }

    /**
     * Los alcances de solo lectura, que son los que se dan sin pensarlo mucho.
     *
     * @return list<self>
     */
    public static function readOnly(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $scope): bool => str_ends_with($scope->value, ':read'),
        ));
    }
}
