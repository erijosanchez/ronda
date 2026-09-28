<?php

declare(strict_types=1);

namespace Ronda\Identity\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Ronda\Directory\Application\Actions\AssignUserToSites;
use Ronda\Directory\Application\Data\SiteAssignmentData;
use Ronda\Identity\Application\Data\PlannedUser;
use Ronda\Identity\Application\Data\UserImportPlan;
use Ronda\Identity\Domain\Models\User;
use Ronda\Platform\Domain\Csv\ImportResult;
use Ronda\Platform\Domain\Exceptions\InvalidImportPlan;

/**
 * Escribe un plan de personas ya validado. RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * Todo o nada, en una sola transaccion (regla 3): personas, roles y sedes son
 * tres tablas, y una persona creada sin sus roles entra pero no puede hacer
 * nada, sin que nadie sepa que le falta.
 *
 * NADIE TRAE CONTRASENA EN EL CSV. A quien se da de alta se le pone una
 * aleatoria que no ve nadie, y entra por «olvide mi contrasena», que manda el
 * correo a su buzon. Aceptar una columna `contrasena` seria repartir las
 * claves de todo el personal en una hoja de calculo que acabara reenviada por
 * correo.
 *
 * A quien ya existe NO se le toca la contrasena: importar corrige datos, no
 * echa a la gente de su sesion.
 */
final readonly class ImportUsers
{
    public function __construct(
        private ConnectionInterface $connection,
        private AssignUserToSites $assign,
    ) {}

    /**
     * @throws InvalidImportPlan
     */
    public function __invoke(UserImportPlan $plan): ImportResult
    {
        if (! $plan->isValid()) {
            throw InvalidImportPlan::withIssues(count($plan->issues));
        }

        return $this->connection->transaction(function () use ($plan): ImportResult {
            $creadas = 0;
            $actualizadas = 0;

            foreach ($plan->users as $planificada) {
                $persona = $planificada->isUpdate()
                    ? $this->update($planificada)
                    : $this->create($planificada);

                if (! $persona instanceof User) {
                    continue;
                }

                $planificada->isUpdate() ? $actualizadas++ : $creadas++;

                if ($planificada->siteIds !== null) {
                    ($this->assign)($persona, array_map(
                        static fn (int $id): SiteAssignmentData => new SiteAssignmentData($id),
                        $planificada->siteIds,
                    ));
                }
            }

            return new ImportResult($creadas, $actualizadas);
        });
    }

    private function create(PlannedUser $planned): User
    {
        $persona = User::create([
            ...$planned->data->toAttributes(),
            // Larga y aleatoria: no la sabe nadie, ni hace falta. El cast
            // `hashed` del modelo la guarda cifrada.
            'password' => Str::password(32),
        ]);

        $persona->syncRoles($planned->data->roleNames());

        return $persona;
    }

    private function update(PlannedUser $planned): ?User
    {
        $persona = User::query()->find($planned->existingId);

        if (! $persona instanceof User) {
            return null;
        }

        // `toAttributes()` omite la contrasena cuando el DTO no la trae, y el
        // importador nunca la trae.
        $persona->update($planned->data->toAttributes());
        $persona->syncRoles($planned->data->roleNames());

        return $persona;
    }
}
