<?php

declare(strict_types=1);

namespace Ronda\Directory\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Ronda\Directory\Application\Data\SiteAssignmentData;
use Ronda\Identity\Domain\Models\User;

/**
 * Fija en que sedes trabaja una persona. RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * Sustituye la lista entera en vez de anadir y quitar por separado: la pantalla
 * envia el estado final, y resolverlo aqui evita que una asignacion retirada se
 * quede olvidada porque el formulario no la menciono.
 *
 * Va en transaccion (regla 3): `sync()` borra e inserta varias filas, y una
 * interrupcion a medias dejaria a la persona sin acceso a sedes en las que si
 * trabaja.
 */
final readonly class AssignUserToSites
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    /**
     * @param  list<SiteAssignmentData>  $assignments
     */
    public function __invoke(User $user, array $assignments): void
    {
        $payload = [];

        foreach ($assignments as $assignment) {
            $payload[$assignment->siteId] = $assignment->toPivot();
        }

        $this->connection->transaction(
            static fn () => $user->sites()->sync($payload),
        );
    }
}
