<?php

declare(strict_types=1);

namespace Ronda\Identity\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Ronda\Identity\Application\Data\UserData;
use Ronda\Identity\Domain\Models\User;

/**
 * Da de alta a una persona del cliente. RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * Escribe en `users` y en `model_has_roles`, asi que va en transaccion
 * (regla 3): un usuario creado sin sus roles entra pero no puede hacer nada, y
 * nadie sabe que le falta.
 *
 * Se inyecta ConnectionInterface en vez de la facade DB: la capa de aplicacion
 * no puede depender de facades.
 */
final readonly class CreateUser
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function __invoke(UserData $data): User
    {
        return $this->connection->transaction(function () use ($data): User {
            $user = User::create($data->toAttributes());

            $user->syncRoles($data->roleNames());

            return $user;
        });
    }
}
