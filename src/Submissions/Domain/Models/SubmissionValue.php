<?php

declare(strict_types=1);

namespace Ronda\Submissions\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Copia tipada de un campo reportable. ADR 0012.
 *
 * No es la fuente de verdad: lo es `submissions.data`. Existe para filtros y
 * KPI sin tocar el JSONB.
 *
 * @property int $id
 * @property int $submission_id
 * @property string $field_key
 * @property string|null $value_text
 * @property string|null $value_numeric decimal: llega como texto, nunca float
 * @property Carbon|null $value_date
 * @property bool|null $value_bool
 */
final class SubmissionValue extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['submission_id', 'field_key', 'value_text', 'value_numeric', 'value_date', 'value_bool'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_numeric' => 'decimal:4',
            'value_date' => 'date',
            'value_bool' => 'boolean',
        ];
    }
}
