<?php

declare(strict_types=1);

namespace Ronda\Identity\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Ronda\Identity\Domain\Exceptions\CannotDeleteLastOwner;
use Ronda\Identity\Domain\Exceptions\CannotDeleteSelf;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\RoleName;

/**
 * Da de baja a una persona del cliente.
 *
 * Borrado logico (sec. 8.6): el historial de envios y revisiones de esa
 * persona tiene que seguir en pie, o el sistema deja de ser auditable.
 *
 * Aqui viven los dos invariantes que la Policy NO puede sostener, porque
 * `Gate::before` concede todo al propietario antes de consultarla:
 *
 *   - nadie se borra a si mismo;
 *   - el cliente no puede quedarse sin propietario.
 */
final readonly class DeleteUser
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function __invoke(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw CannotDeleteSelf::make();
        }

        // La comprobacion y el borrado van juntos en una transaccion aunque
        // solo se escriba una tabla: sin ella, dos bajas simultaneas de los dos
        // ultimos propietarios contarian ambas «quedan dos» y dejarian al
        // cliente sin ninguno.
        $this->connection->transaction(function () use ($target): void {
            if ($this->isLastOwner($target)) {
                throw CannotDeleteLastOwner::make();
            }

            $target->delete();
        });
    }

    private function isLastOwner(User $target): bool
    {
        // Se consulta la relacion en vez de `hasRole()`: ese metodo solo puede
        // usarse dentro de una Policy (regla 4), y aqui la pregunta no es «se
        // le permite algo» sino «es el ultimo propietario», que es negocio.
        $esPropietario = $target->roles()
            ->where('name', RoleName::Owner->value)
            ->exists();

        if (! $esPropietario) {
            return false;
        }

        // Se seleccionan las filas en vez de contarlas: PostgreSQL rechaza
        // `FOR UPDATE` junto a una funcion de agregacion. Basta con encontrar
        // uno, de ahi el limit.
        $otroPropietario = User::query()
            ->whereKeyNot($target->getKey())
            ->whereHas('roles', fn ($query) => $query->where('name', RoleName::Owner->value))
            ->lockForUpdate()
            ->limit(1)
            ->get(['id']);

        return $otroPropietario->isEmpty();
    }
}
