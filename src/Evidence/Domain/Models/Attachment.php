<?php

declare(strict_types=1);

namespace Ronda\Evidence\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Ronda\Evidence\Domain\EvidenceKind;
use Ronda\Identity\Domain\Models\User;
use Ronda\Submissions\Domain\Models\Submission;

/**
 * Un archivo de evidencia. RONDA-PLAN-MAESTRO.md sec. 9.5 y ADR 0009.
 *
 * No se modifica despues de crearse: lo que se guardo es lo que se entrego, y
 * su SHA-256 lo demuestra.
 *
 * @property int $id
 * @property int $submission_id
 * @property string $field_key
 * @property EvidenceKind $kind
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $bytes
 * @property string $sha256
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $location_source
 * @property int|null $distance_meters
 * @property Carbon|null $captured_at
 * @property int $uploaded_by
 * @property string|null $ip_address
 * @property Carbon $created_at
 */
final class Attachment extends Model
{
    /**
     * A partir de cuantos metros de la sede la revision lo senala.
     */
    public const int FAR_FROM_SITE_METERS = 500;

    /**
     * Cuanto antes de la entrega puede haberse tomado una foto sin que se
     * senale. Mas que eso huele a foto reutilizada.
     */
    public const int STALE_CAPTURE_HOURS = 24;

    /** @var list<string> */
    protected $fillable = [
        'submission_id', 'field_key', 'kind', 'disk', 'path', 'original_name', 'mime_type',
        'bytes', 'sha256', 'latitude', 'longitude', 'location_source', 'distance_meters',
        'captured_at', 'uploaded_by', 'ip_address',
    ];

    /**
     * @return BelongsTo<Submission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return $this->kind->isImage($this->mime_type);
    }

    public function isFarFromSite(): bool
    {
        return $this->distance_meters !== null && $this->distance_meters > self::FAR_FROM_SITE_METERS;
    }

    /**
     * Si la foto se tomo mucho antes de entregarse.
     */
    public function isStaleAt(CarbonImmutable|Carbon $submittedAt): bool
    {
        return $this->captured_at !== null
            && $this->captured_at->diffInHours($submittedAt, false) > self::STALE_CAPTURE_HOURS;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => EvidenceKind::class,
            'bytes' => 'integer',
            'distance_meters' => 'integer',
            'captured_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }
}
