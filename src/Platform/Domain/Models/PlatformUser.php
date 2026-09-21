<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Alguien del equipo de Ronda. RONDA-PLAN-MAESTRO.md sec. 15.4
 *
 * No es un `User`: los usuarios viven en la base de cada cliente y esta cuenta
 * no pertenece a ninguno. Compartir tabla convertiria cualquier fallo de
 * aislamiento en una escalada al back-office.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property bool $is_active
 * @property CarbonImmutable|null $last_login_at
 */
final class PlatformUser extends Authenticatable implements AuthenticatableContract
{
    use CentralConnection;
    use Notifiable;

    protected $guarded = [];

    /**
     * @var list<string>
     */
    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /**
     * Si esta cuenta puede entrar hoy.
     *
     * Dos condiciones, y las dos obligatorias (sec. 15.4): que siga activa y
     * que tenga el segundo factor confirmado. Una cuenta de soporte sin 2FA no
     * es una cuenta a medio configurar: es una llave maestra con contrasena.
     */
    public function canUseBackOffice(): bool
    {
        return $this->is_active && $this->two_factor_confirmed_at !== null;
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            // Cifrados: el secreto TOTP permite generar los codigos, asi que
            // en claro valdria tanto como la contrasena.
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted',
            'two_factor_confirmed_at' => 'immutable_datetime',
            'last_login_at' => 'immutable_datetime',
            'is_active' => 'boolean',
        ];
    }
}
