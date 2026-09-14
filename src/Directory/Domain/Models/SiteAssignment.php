<?php

declare(strict_types=1);

namespace Ronda\Directory\Domain\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * La fila que une a una persona con una sede. RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * Tiene modelo propio porque no es un pivote vacio: lleva el cargo y el rol que
 * esa persona ocupa en esa sede concreta. Sin el, esos dos datos se leen como
 * propiedades sin tipo y el analisis estatico no puede comprobarlos.
 *
 * @property int|null $position_id
 * @property string|null $role
 */
final class SiteAssignment extends Pivot
{
    public $incrementing = true;

    protected $table = 'user_site';
}
