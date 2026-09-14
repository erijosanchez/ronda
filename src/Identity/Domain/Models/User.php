<?php

declare(strict_types=1);

namespace Ronda\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Identity\Database\Factories\UserFactory;
use Ronda\Identity\Domain\UserStatus;
use Spatie\Permission\Traits\HasRoles;

/**
 * Usuario de un cliente. Vive en la base del tenant (sec. 8.3), asi que la
 * conexion por defecto ya apunta a la base correcta cuando el middleware de
 * tenancy ha identificado el dominio. No lleva `tenant_id`: no hace falta.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property UserStatus $status
 * @property Carbon|null $last_login_at
 * @property Carbon|null $deleted_at
 */
final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;
    use SoftDeletes;
    use TwoFactorAuthenticatable;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'status',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Sedes asignadas a esta persona, con su cargo y su rol en cada una.
     *
     * La tabla se nombra a mano: el plan la llama `user_site` y Laravel, por
     * orden alfabetico, buscaria `site_user`.
     *
     * @return BelongsToMany<Site, $this>
     */
    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'user_site')
            ->withPivot(['position_id', 'role'])
            ->withTimestamps();
    }

    /**
     * El modelo no vive en App\Models, asi que la resolucion por convencion de
     * Laravel no encuentra su factory.
     *
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return UserFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            // Dato sensible: se guarda cifrado (sec. 8.6). No se puede buscar
            // por telefono en SQL, y se asume.
            'phone' => 'encrypted',
            'status' => UserStatus::class,
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
