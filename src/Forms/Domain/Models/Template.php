<?php

declare(strict_types=1);

namespace Ronda\Forms\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ronda\Forms\Domain\TemplateStatus;

/**
 * Que se pide. La primera de las seis piezas del motor (sec. 9.1).
 *
 * Guarda la identidad estable; el contenido del formulario vive en sus
 * versiones (ADR 0012).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property TemplateStatus $status
 * @property int|null $current_version_id
 */
final class Template extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['code', 'name', 'description', 'status'];

    /**
     * @return HasMany<TemplateVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class);
    }

    /**
     * La version con la que se responde hoy.
     *
     * @return BelongsTo<TemplateVersion, $this>
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class, 'current_version_id');
    }

    /**
     * El borrador en curso, si lo hay. Solo puede haber uno: PublishVersion lo
     * consume al publicar.
     */
    public function draft(): ?TemplateVersion
    {
        return $this->versions()
            ->whereNull('published_at')
            ->orderByDesc('number')
            ->first();
    }

    public function nextVersionNumber(): int
    {
        return (int) $this->versions()->max('number') + 1;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TemplateStatus::class,
        ];
    }
}
