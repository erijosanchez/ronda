<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Scheduling\Domain\States\ObligationStatus;
use Spatie\ModelStates\HasStates;

/**
 * Una entrega esperada, materializada por adelantado. ADR 0008.
 *
 * Es una FOTO: los tres instantes se calcularon con la programacion tal como
 * era al materializar. Si la programacion cambia despues, esta fila no se
 * mueve.
 *
 * @property int $id
 * @property int $schedule_id
 * @property int $site_id
 * @property int $template_id
 * @property Carbon $occurrence_date
 * @property Carbon $opens_at
 * @property Carbon $due_at
 * @property Carbon $closes_at
 * @property ObligationStatus $status
 * @property string|null $excuse_reason
 * @property int|null $excused_by
 */
final class Obligation extends Model
{
    use HasStates;

    /** @var list<string> */
    protected $fillable = [
        'schedule_id', 'site_id', 'template_id', 'occurrence_date',
        'opens_at', 'due_at', 'closes_at', 'status',
    ];

    /**
     * @return BelongsTo<Schedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * La plantilla que se pide. La columna se copia al materializar (ver la
     * migracion), asi que no hace falta pasar por la programacion.
     *
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ObligationStatus::class,
            'occurrence_date' => 'date',
            'opens_at' => 'datetime',
            'due_at' => 'datetime',
            'closes_at' => 'datetime',
        ];
    }
}
