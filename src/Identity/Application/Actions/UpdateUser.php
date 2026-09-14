<?php

declare(strict_types=1);

namespace Ronda\Identity\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Ronda\Identity\Application\Data\UserData;
use Ronda\Identity\Domain\Models\User;

/**
 * Modifica a una persona del cliente.
 *
 * Como en el alta, toca dos tablas y va en transaccion. Recibe el usuario ya
 * resuelto: buscarlo por id aqui dentro se saltaria la Policy que lo autorizo.
 */
final readonly class UpdateUser
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function __invoke(User $user, UserData $data): User
    {
        return $this->connection->transaction(function () use ($user, $data): User {
            $user->update($data->toAttributes());

            $user->syncRoles($data->roleNames());

            return $user->refresh();
        });
    }
}
