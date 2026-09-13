<?php

declare(strict_types=1);

namespace Ronda\Directory\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ronda\Identity\Domain\Models\User;

/**
 * Agrupacion de sedes, con jerarquia propia.
 * RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * @property int $id
 * @property string $name
 * @property int|null $parent_id
 * @property int|null $manager_id
 */
final class Zone extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['name', 'parent_id', 'manager_id'];

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * @return HasMany<Site, $this>
     */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }
}
