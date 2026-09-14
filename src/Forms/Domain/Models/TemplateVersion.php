<?php

declare(strict_types=1);

namespace Ronda\Forms\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Ronda\Forms\Domain\ValueObjects\FormSchema;
use Ronda\Identity\Domain\Models\User;

/**
 * Una version del formulario. Ver docs/adr/0012.
 *
 * Toda version nace publicada: el plan (sec. 9.2) no contempla borradores, y
 * `published_at` es NOT NULL. Una vez creada NO se modifica, que es lo que
 * permite que un envio de marzo se lea con la plantilla de marzo.
 *
 * @property int $id
 * @property int $template_id
 * @property int $number
 * @property array<int, array<string, mixed>> $schema
 * @property Carbon $published_at
 * @property int|null $published_by
 */
final class TemplateVersion extends Model
{
    /** @var list<string> */
    protected $fillable = ['template_id', 'number', 'schema', 'published_at', 'published_by'];

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * El esquema como objeto de dominio, ya validado.
     *
     * Se reconstruye en cada llamada a proposito: devolver un value object
     * cacheado invita a mutarlo, y una version publicada es inmutable.
     */
    public function formSchema(): FormSchema
    {
        // Se reindexa antes de pasarlo: el JSONB puede volver con claves no
        // contiguas y FormSchema espera una lista.
        /** @var list<array<string, mixed>> $schema */
        $schema = array_values($this->schema);

        return FormSchema::fromArray($schema);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'published_at' => 'datetime',
            'number' => 'integer',
        ];
    }
}
