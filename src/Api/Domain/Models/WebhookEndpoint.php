<?php

declare(strict_types=1);

namespace Ronda\Api\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ronda\Api\Domain\Webhooks\WebhookEvent;

/**
 * Una direccion del cliente a la que Ronda manda avisos.
 * RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * El secreto va cifrado: con el se firma cada envio, asi que en claro
 * permitiria falsificar avisos a quien leyera la base.
 *
 * @property int $id
 * @property string $url
 * @property string|null $description
 * @property string $secret
 * @property list<string> $subscribed_events
 * @property bool $is_active
 * @property int $consecutive_failures
 * @property CarbonImmutable|null $last_success_at
 * @property CarbonImmutable|null $last_failure_at
 */
final class WebhookEndpoint extends Model
{
    /** Tras cuantos fallos seguidos se apaga solo. */
    public const int FAILURE_LIMIT = 15;

    protected $guarded = [];

    /**
     * @var list<string>
     */
    protected $hidden = ['secret'];

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function listensTo(WebhookEvent $event): bool
    {
        return $this->is_active && in_array($event->value, $this->subscribed_events, true);
    }

    /**
     * Si ya fallo demasiadas veces seguidas.
     *
     * Seguir llamando cada minuto a una URL que lleva dias muerta es maltratar
     * un servidor ajeno y llenar el registro de ruido.
     */
    public function exhausted(): bool
    {
        return $this->consecutive_failures >= self::FAILURE_LIMIT;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subscribed_events' => 'array',
            'secret' => 'encrypted',
            'is_active' => 'boolean',
            'last_success_at' => 'immutable_datetime',
            'last_failure_at' => 'immutable_datetime',
        ];
    }
}
