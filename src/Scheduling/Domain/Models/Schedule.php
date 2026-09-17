<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Directory\Domain\Models\Zone;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Scheduling\Domain\ScheduleScope;
use Ronda\Scheduling\Domain\ValueObjects\Recurrence;
use Ronda\Scheduling\Domain\ValueObjects\RecurrencePattern;
use Ronda\Scheduling\Domain\ValueObjects\TimeWindow;

/**
 * Cuando y a quien se le pide una plantilla. La segunda pieza del motor
 * (sec. 9.1).
 *
 * @property int $id
 * @property int $template_id
 * @property string $name
 * @property ScheduleScope $scope
 * @property int|null $zone_id
 * @property string $rrule
 * @property string $window_start
 * @property string $window_end
 * @property int $tolerance_minutes
 * @property bool $skip_holidays
 * @property bool $active
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 */
final class Schedule extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'template_id', 'name', 'scope', 'zone_id', 'rrule', 'window_start', 'window_end',
        'tolerance_minutes', 'skip_holidays', 'active', 'starts_on', 'ends_on',
    ];

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Lista cerrada de sedes, solo para el alcance `sites`.
     *
     * @return BelongsToMany<Site, $this>
     */
    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class);
    }

    /**
     * @return HasMany<Obligation, $this>
     */
    public function obligations(): HasMany
    {
        return $this->hasMany(Obligation::class);
    }

    public function recurrence(): Recurrence
    {
        return Recurrence::fromString($this->rrule);
    }

    /**
     * La regla como la cuenta una persona, para mostrarla y editarla.
     */
    public function recurrencePattern(): RecurrencePattern
    {
        return RecurrencePattern::fromRecurrence($this->recurrence());
    }

    public function window(): TimeWindow
    {
        return TimeWindow::between($this->window_start, $this->window_end);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => ScheduleScope::class,
            'tolerance_minutes' => 'integer',
            'skip_holidays' => 'boolean',
            'active' => 'boolean',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }
}
