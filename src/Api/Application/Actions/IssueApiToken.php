<?php

declare(strict_types=1);

namespace Ronda\Api\Application\Actions;

use Carbon\CarbonImmutable;
use Ronda\Api\Domain\ApiScope;
use Ronda\Identity\Domain\Models\User;

/**
 * Emite un token de la API. RONDA-PLAN-MAESTRO.md sec. 13.1
 *
 * Devuelve el token EN CLARO una sola vez: en la base solo queda su hash, asi
 * que no hay pantalla que pueda volver a mostrarlo. Es lo que hace que un
 * volcado de la base no entregue las llaves de nadie.
 *
 * El token nace atado a una persona, no al cliente: lo que puede hacer sale de
 * cruzar sus alcances con las Policies de esa persona. Un token «de la
 * empresa», sin dueno, seria un permiso sin responsable.
 */
final readonly class IssueApiToken
{
    /**
     * @param  list<ApiScope>  $scopes
     * @return array{token: string, id: int}
     */
    public function __invoke(User $owner, string $name, array $scopes, ?CarbonImmutable $expiresAt = null): array
    {
        $alcances = array_values(array_unique(array_map(
            static fn (ApiScope $scope): string => $scope->value,
            $scopes,
        )));

        $nuevo = $owner->createToken(trim($name), $alcances, $expiresAt);

        return [
            'token' => $nuevo->plainTextToken,
            'id' => (int) $nuevo->accessToken->getKey(),
        ];
    }
}
