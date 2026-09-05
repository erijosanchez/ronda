<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Models;

use Ronda\Platform\Domain\States\TenantStatus;
use RuntimeException;
use Spatie\ModelStates\HasStates;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * Un cliente del SaaS. Vive en la base central; sus datos, en la suya propia.
 * RONDA-PLAN-MAESTRO.md sec. 8.2
 *
 * El identificador es un ULID, no un UUID: ordena por tiempo de creacion, lo
 * que hace que el indice primario no se fragmente y que el nombre de la base
 * (`ronda_tnt_<ulid>`) sea legible al listarlas. Lo genera la Action
 * CreateTenant, no el modelo.
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property TenantStatus $status
 */
final class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;
    use HasStates;

    /**
     * Columnas propias: todo lo demas que se guarde en el modelo cae en la
     * columna `data` (JSONB) que gestiona stancl/tenancy.
     *
     * @return list<string>
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'status',
            'trial_ends_at',
        ];
    }

    /**
     * Nombre de la base de datos de este tenant.
     *
     * Se expone aqui porque lo usan tanto la provision como el back-office, y
     * reconstruirlo a mano en cada sitio es como se acaba con dos formatos.
     */
    public function databaseName(): string
    {
        // getName() devuelve null si el tenant no tiene credenciales de base
        // hechas todavia. Que eso ocurra significa que alguien llamo aqui antes
        // de provisionar, y es mejor enterarse en el acto que operar sobre null.
        return $this->database()->getName()
            ?? throw new RuntimeException("El tenant {$this->id} no tiene base de datos asignada.");
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'trial_ends_at' => 'datetime',
            'data' => 'array',
        ];
    }
}
