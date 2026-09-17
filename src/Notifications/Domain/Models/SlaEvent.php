<?php

declare(strict_types=1);

namespace Ronda\Notifications\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Ronda\Notifications\Domain\NotificationTopic;

/**
 * Que aviso se mando ya, sobre que y en que peldano.
 * RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * Es lo que permite repasar el parque entero cada hora sin repetir avisos. Solo
 * se inserta.
 *
 * @property int $id
 * @property string $subject_type
 * @property int $subject_id
 * @property NotificationTopic $topic
 * @property int $level
 * @property int $recipients
 * @property Carbon $occurred_at
 */
final class SlaEvent extends Model
{
    /** @var list<string> */
    protected $fillable = ['subject_type', 'subject_id', 'topic', 'level', 'recipients', 'occurred_at'];

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'topic' => NotificationTopic::class,
            'level' => 'integer',
            'recipients' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }
}
