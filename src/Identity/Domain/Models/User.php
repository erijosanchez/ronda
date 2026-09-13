<?php

declare(strict_types=1);

namespace Ronda\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Identity\Database\Factories\UserFactory;
use Spatie\Permission\Traits\HasRoles;

/**
 * Usuario de un cliente. Vive en la base del tenant (sec. 8.3), asi que la
 * conexion por defecto ya apunta a la base correcta cuando el middleware de
 * tenancy ha identificado el dominio. No lleva `tenant_id`: no hace falta.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 */
final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
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
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
