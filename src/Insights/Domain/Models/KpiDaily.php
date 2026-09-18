<?php

declare(strict_types=1);

namespace Ronda\Insights\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Forms\Domain\Models\Template;

/**
 * Una fila de KPI: lo que paso un dia, en una sede, con una plantilla.
 * RONDA-PLAN-MAESTRO.md sec. 9.6
 *
 * La escribe el job (RecalculateKpis) y la leen las consultas del tablero.
 * Nadie la edita a mano.
 *
 * @property int $id
 * @property Carbon $kpi_date
 * @property int $site_id
 * @property int $template_id
 * @property int $fulfilled
 * @property int $missed
 * @property int $excused
 * @property int $on_time
 * @property int $late
 * @property int $minutes_late_sum
 * @property int $approved
 * @property int $rejected
 * @property int $approved_first_try
 * @property int $reviews_resolved
 * @property int $review_minutes_sum
 */
final class KpiDaily extends Model
{
    protected $table = 'kpi_daily';

    /** @var list<string> */
    protected $fillable = [
        'kpi_date', 'site_id', 'template_id',
        'fulfilled', 'missed', 'excused',
        'on_time', 'late', 'minutes_late_sum',
        'approved', 'rejected', 'approved_first_try',
        'reviews_resolved', 'review_minutes_sum',
    ];

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kpi_date' => 'date',
            'fulfilled' => 'integer',
            'missed' => 'integer',
            'excused' => 'integer',
            'on_time' => 'integer',
            'late' => 'integer',
            'minutes_late_sum' => 'integer',
            'approved' => 'integer',
            'rejected' => 'integer',
            'approved_first_try' => 'integer',
            'reviews_resolved' => 'integer',
            'review_minutes_sum' => 'integer',
        ];
    }
}
