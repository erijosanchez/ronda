<?php

declare(strict_types=1);

namespace Ronda\Directory\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cargo dentro de la organizacion del cliente (encargado, supervisor de zona,
 * jefe de operaciones). RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * No confundir con los roles de spatie: un cargo describe el puesto en el
 * organigrama, un rol concede permisos. Un mismo cargo puede tener roles
 * distintos segun el cliente.
 *
 * @property int $id
 * @property string $name
 * @property int $level
 */
final class Position extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['name', 'level'];

    /**
     * Cuanta gente ocupa este cargo en alguna sede. Es lo que impide borrarlo
     * por debajo (ver DeletePosition).
     *
     * @return HasMany<SiteAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(SiteAssignment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['level' => 'integer'];
    }
}
