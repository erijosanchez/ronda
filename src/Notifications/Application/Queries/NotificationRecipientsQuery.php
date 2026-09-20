<?php

declare(strict_types=1);

namespace Ronda\Notifications\Application\Queries;

use Illuminate\Contracts\Auth\Access\Gate;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Identity\Domain\Models\User;
use Ronda\Notifications\Domain\Audience;
use Ronda\Scheduling\Domain\Models\Obligation;
use Ronda\Submissions\Domain\Models\Submission;

/**
 * A quien se le manda un aviso. RONDA-PLAN-MAESTRO.md sec. 9.4
 *
 * No consulta roles ni permisos: pregunta a las Policies, una persona a la vez
 * (regla 4). Asi un cliente que reorganice sus roles sigue avisando a quien
 * corresponde, y nadie recibe por correo algo que no podria abrir.
 *
 * Los candidatos salen de la asignacion persona - sede, que es la frontera del
 * sistema; para el ultimo peldano de la escalera, de quien administra el parque
 * entero.
 */
final readonly class NotificationRecipientsQuery
{
    public function __construct(
        private Gate $gate,
    ) {}

    /**
     * @return list<User>
     */
    public function forObligation(Audience $audience, Obligation $obligation): array
    {
        $site = Site::query()->withoutGlobalScopes()->find($obligation->site_id);

        if (! $site instanceof Site) {
            return [];
        }

        return match ($audience) {
            Audience::Site => $this->assigned($site, fn (User $user): bool => $this->gate->forUser($user)->allows('submit', $obligation)),
            Audience::Reviewers => $this->reviewers(
                $site,
                fn (User $user): bool => $this->gate->forUser($user)->allows('viewInbox', Submission::class),
            ),
            Audience::Administrators => $this->administrators(),
        };
    }

    /**
     * @return list<User>
     */
    public function forSubmission(Audience $audience, Submission $submission): array
    {
        $site = Site::query()->withoutGlobalScopes()->find($submission->site_id);

        if (! $site instanceof Site) {
            return [];
        }

        return match ($audience) {
            Audience::Site => $this->assigned($site, fn (User $user): bool => $this->gate->forUser($user)->allows('correct', $submission)),
            Audience::Reviewers => $this->reviewers(
                $site,
                fn (User $user): bool => $this->gate->forUser($user)->allows('review', $submission),
            ),
            Audience::Administrators => $this->administrators(),
        };
    }

    /**
     * Quien revisa lo de esta sede.
     *
     * Primero, las personas asignadas a ella: son las que llevan esa sede.
     *
     * Si ninguna puede —porque la unica asignada es quien entrego, que no
     * revisa lo suyo, o porque nadie tiene el permiso— se cae a quien
     * administra el parque entero, que SI puede revisarlo.
     *
     * Ese respaldo existe por el caso de una empresa chica, que es justo con
     * la que se empieza: una sede, un encargado y la duena. Sin el, el reporte
     * se queda esperando revision y no se entera nadie, que es exactamente lo
     * que este producto promete que no pasa.
     *
     * En una empresa con supervisores asignados no cambia nada: si hay alguien
     * asignado que puede revisar, el respaldo no se usa y no se avisa de mas.
     *
     * @param  callable(User): bool  $puede
     * @return list<User>
     */
    private function reviewers(Site $site, callable $puede): array
    {
        $asignados = $this->assigned($site, $puede);

        if ($asignados !== []) {
            return $asignados;
        }

        return array_values(array_filter($this->administrators(), $puede));
    }

    /**
     * Personas asignadas a la sede que pasan la comprobacion.
     *
     * @param  callable(User): bool  $puede
     * @return list<User>
     */
    private function assigned(Site $site, callable $puede): array
    {
        /** @var list<User> $usuarios */
        $usuarios = $site->users()->get()->all();

        return array_values(array_filter($usuarios, $puede));
    }

    /**
     * Quien ve el parque entero: el ultimo peldano. `viewAll` es de SitePolicy,
     * y el propietario pasa por OwnerGate.
     *
     * @return list<User>
     */
    private function administrators(): array
    {
        $administradores = [];

        // Por lotes: un cliente grande tiene cientos de usuarios y esto corre
        // dentro de un job, no en una pantalla.
        foreach (User::query()->lazy(200) as $user) {
            if ($this->gate->forUser($user)->allows('viewAll', Site::class)) {
                $administradores[] = $user;
            }
        }

        return $administradores;
    }
}
