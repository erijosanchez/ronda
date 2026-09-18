<?php

declare(strict_types=1);

namespace Ronda\Insights\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Ronda\Identity\Domain\Models\User;
use Ronda\Insights\Domain\ExportStatus;

/**
 * Un encargo de exportacion. RONDA-PLAN-MAESTRO.md sec. 13
 *
 * @property int $id
 * @property string $type
 * @property ExportStatus $status
 * @property array<string, mixed> $filters
 * @property int $requested_by
 * @property string|null $disk
 * @property string|null $path
 * @property string|null $file_name
 * @property int|null $rows
 * @property int|null $bytes
 * @property string|null $error
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 */
final class Export extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'type', 'status', 'filters', 'requested_by',
        'disk', 'path', 'file_name', 'rows', 'bytes', 'error', 'completed_at',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ExportStatus::class,
            'filters' => 'array',
            'rows' => 'integer',
            'bytes' => 'integer',
            'completed_at' => 'datetime',
        ];
    }
}
