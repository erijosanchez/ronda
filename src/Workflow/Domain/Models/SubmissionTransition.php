<?php

declare(strict_types=1);

namespace Ronda\Workflow\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Ronda\Identity\Domain\Models\User;
use Ronda\Submissions\Domain\Models\Submission;

/**
 * Un cambio de estado de un envio: quien, cuando y por que.
 * RONDA-PLAN-MAESTRO.md sec. 9.4
 *
 * Solo se inserta. No lleva `updated_at` porque nunca se actualiza.
 *
 * @property int $id
 * @property int $submission_id
 * @property string|null $from_state
 * @property string $to_state
 * @property int $actor_id
 * @property string|null $comment
 * @property Carbon $created_at
 */
final class SubmissionTransition extends Model
{
    public const null UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['submission_id', 'from_state', 'to_state', 'actor_id', 'comment'];

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
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
