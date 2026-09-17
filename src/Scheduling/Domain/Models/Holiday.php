<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Un dia no laborable. RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * @property int $id
 * @property Carbon $date
 * @property string $name
 * @property string $scope
 * @property string|null $region
 * @property string $source
 */
final class Holiday extends Model
{
    /** @var list<string> */
    protected $fillable = ['date', 'name', 'scope', 'region', 'source'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
