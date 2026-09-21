<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Una entrada del registro de suplantaciones.
 * RONDA-PLAN-MAESTRO.md sec. 15.4
 *
 * Se llama `ImpersonationEntry` y no `ImpersonationLog` porque un modelo es una
 * fila, no el libro entero.
 *
 * No se borra nunca: es la respuesta a «¿quien de ustedes entro en mi cuenta,
 * cuando y por que?». Un registro que se puede borrar no es un registro.
 *
 * @property int $id
 * @property int $platform_user_id
 * @property string $tenant_id
 * @property int $impersonated_user_id
 * @property string $impersonated_user_email
 * @property string $reason
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $ended_at
 * @property string|null $ip_address
 */
final class ImpersonationEntry extends Model
{
    use CentralConnection;

    protected $table = 'impersonation_log';

    protected $guarded = [];

    /**
     * @return BelongsTo<PlatformUser, $this>
     */
    public function platformUser(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Si la sesion suplantada sigue viva.
     *
     * Dos condiciones: que no se haya cerrado y que no haya caducado. El
     * tiempo manda aunque nadie pulse «salir», que es justo el caso que hay
     * que cubrir: alguien que cierra el portatil y se va a almorzar.
     */
    public function isOpen(?CarbonImmutable $now = null): bool
    {
        return $this->ended_at === null
            && $this->expires_at->isAfter($now ?? CarbonImmutable::now('UTC'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }
}
