<?php

declare(strict_types=1);

namespace Ronda\Workflow\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Ronda\Identity\Domain\Models\User;
use Ronda\Submissions\Domain\Models\Submission;

/**
 * Un mensaje en la conversacion de un envio. RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * @property int $id
 * @property int $submission_id
 * @property int $author_id
 * @property string $body
 * @property Carbon $created_at
 */
final class SubmissionComment extends Model
{
    /** @var list<string> */
    protected $fillable = ['submission_id', 'author_id', 'body'];

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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
