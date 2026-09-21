<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ronda\Platform\Domain\States\TenantStatus;
use RuntimeException;
use Spatie\ModelStates\HasStates;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Domain;
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
            'plan_id',
            'trial_ends_at',
            'provisioned_at',
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
     * Si ya se puede entrar: la base existe, esta migrada, sembrada y el
     * propietario creado (sec. 15.3). Entre el registro y esto pasan unos
     * segundos, y la pantalla de espera pregunta por aqui.
     */
    public function isReady(): bool
    {
        return $this->provisioned_at !== null;
    }

    /**
     * Los dominios por los que se llega a este cliente.
     *
     * La relacion ya la trae el trait del paquete, pero sin tipo de retorno: el
     * analisis estatico no la reconoce como relacion y no puede comprobar un
     * `with('domains')`. Se redeclara con su tipo y respetando el modelo
     * configurable, que es lo unico que aportaba la del paquete.
     *
     * @return HasMany<Domain, $this>
     */
    public function domains(): HasMany
    {
        /** @var class-string<Domain> $modelo */
        $modelo = config('tenancy.domain_model', Domain::class);

        return $this->hasMany($modelo, 'tenant_id');
    }

    /**
     * El plan contratado, o null mientras el cliente esta en prueba y no ha
     * elegido ninguno (sec. 8.2).
     *
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'trial_ends_at' => 'datetime',
            'provisioned_at' => 'datetime',
            'data' => 'array',
        ];
    }
}
