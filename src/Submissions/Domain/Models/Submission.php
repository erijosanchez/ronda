<?php

declare(strict_types=1);

namespace Ronda\Submissions\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Forms\Domain\Models\TemplateVersion;
use Ronda\Identity\Domain\Models\User;
use Ronda\Scheduling\Domain\Models\Obligation;
use Ronda\Submissions\Domain\States\SubmissionState;
use Spatie\ModelStates\HasStates;

/**
 * Lo que llego. La cuarta pieza del motor (sec. 9.1).
 *
 * `data` es la fuente de verdad (ADR 0012). Apunta a la version del formulario
 * con la que se respondio, asi que se puede leer igual aunque la plantilla haya
 * cambiado despues.
 *
 * @property int $id
 * @property int $template_id
 * @property int $template_version_id
 * @property int $site_id
 * @property int|null $obligation_id
 * @property int $author_id
 * @property SubmissionState $state
 * @property array<string, mixed> $data
 * @property Carbon $submitted_at
 * @property bool $is_late
 * @property int $minutes_late
 */
final class Submission extends Model
{
    use HasStates;

    /** @var list<string> */
    protected $fillable = [
        'template_id', 'template_version_id', 'site_id', 'obligation_id', 'author_id',
        'state', 'data', 'submitted_at', 'is_late', 'minutes_late',
    ];

    /**
     * @return BelongsTo<TemplateVersion, $this>
     */
    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class);
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return BelongsTo<Obligation, $this>
     */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return HasMany<SubmissionValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(SubmissionValue::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => SubmissionState::class,
            'data' => 'array',
            'submitted_at' => 'datetime',
            'is_late' => 'boolean',
            'minutes_late' => 'integer',
        ];
    }
}
