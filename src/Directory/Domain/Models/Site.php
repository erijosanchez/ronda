<?php

declare(strict_types=1);

namespace Ronda\Directory\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Ronda\Directory\Database\Factories\SiteFactory;
use Ronda\Directory\Domain\Scopes\AssignedSitesScope;
use Ronda\Identity\Domain\Models\User;

/**
 * Una sucursal del cliente: el sitio donde se hace la ronda.
 * RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * Lleva el scope de frontera por sede siempre puesto. La segunda capa es
 * SitePolicy (sec. 10.3).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int|null $zone_id
 * @property string|null $address
 * @property string|null $latitude decimal: llega como texto, no como float
 * @property string|null $longitude
 * @property string $timezone
 * @property-read SiteAssignment|null $pivot  solo al cargarse por la relacion
 * @property string|null $opens_at hora local de la sede, `HH:MM:SS`
 * @property string|null $closes_at
 * @property Carbon|null $active_from
 * @property Carbon|null $active_until
 * @property Carbon|null $deleted_at
 */
#[ScopedBy(AssignedSitesScope::class)]
final class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'code',
        'name',
        'zone_id',
        'address',
        'latitude',
        'longitude',
        'timezone',
        'opens_at',
        'closes_at',
        'active_from',
        'active_until',
    ];

    /**
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Personas asignadas a esta sede, con su cargo y su rol aqui.
     *
     * La tabla se nombra a mano: el plan la llama `user_site` y Laravel, por
     * orden alfabetico, buscaria `site_user`.
     *
     * @return BelongsToMany<User, $this, SiteAssignment>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_site')
            ->using(SiteAssignment::class)
            ->withPivot(['position_id', 'role'])
            ->withTimestamps();
    }

    /**
     * Sedes vigentes hoy. Una sede cerrada no se borra: conserva su historial,
     * asi que hay que excluirla al listar lo operativo.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query
            ->where(fn (Builder $q): Builder => $q->whereNull('active_from')->orWhere('active_from', '<=', $today))
            ->where(fn (Builder $q): Builder => $q->whereNull('active_until')->orWhere('active_until', '>=', $today));
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SiteFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'active_from' => 'date',
            'active_until' => 'date',
        ];
    }
}
