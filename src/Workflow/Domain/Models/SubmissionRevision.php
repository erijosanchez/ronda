<?php

declare(strict_types=1);

namespace Ronda\Workflow\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Ronda\Submissions\Domain\Models\Submission;

/**
 * Lo que un envio decia antes de corregirse.
 *
 * Una correccion sustituye `submissions.data`, que es la fuente de verdad de lo
 * vigente. Lo que se rechazo queda aqui, con el motivo: sin esto, corregir
 * borraria la prueba de que hubo algo que corregir.
 *
 * @property int $id
 * @property int $submission_id
 * @property int $number
 * @property int $template_version_id
 * @property array<string, mixed> $data
 * @property string|null $rejection_comment
 * @property Carbon $created_at
 */
final class SubmissionRevision extends Model
{
    public const null UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['submission_id', 'number', 'template_version_id', 'data', 'rejection_comment'];

    /**
     * @return BelongsTo<Submission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }
}
