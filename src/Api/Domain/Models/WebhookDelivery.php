<?php

declare(strict_types=1);

namespace Ronda\Api\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un intento de entrega. RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * Es el registro consultable que pide el plan: sin el, «no me llego el aviso»
 * es una discusion sin datos. Con el, se ve que se mando, cuando, que
 * respondio el otro lado y cuantas veces se intento.
 *
 * Guarda el cuerpo enviado y no una referencia al envio: el aviso tiene que
 * poder reenviarse tal cual fue, aunque el reporte haya cambiado despues.
 *
 * @property int $id
 * @property int $webhook_endpoint_id
 * @property string $event
 * @property array<string, mixed> $payload
 * @property string $status
 * @property int $attempts
 * @property int|null $response_status
 * @property string|null $error
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $next_attempt_at
 */
final class WebhookDelivery extends Model
{
    public const string PENDING = 'pending';

    public const string DELIVERED = 'delivered';

    public const string FAILED = 'failed';

    /** Cuantos intentos antes de rendirse con un aviso. */
    public const int MAX_ATTEMPTS = 6;

    protected $guarded = [];

    /**
     * @return BelongsTo<WebhookEndpoint, $this>
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    public function isDelivered(): bool
    {
        return $this->status === self::DELIVERED;
    }

    public function hasFailed(): bool
    {
        return $this->status === self::FAILED;
    }

    /**
     * Cuanto esperar tras el intento numero `$attempt`, en segundos.
     *
     * Exponencial (sec. 13.2): 1, 2, 4, 8 y 16 minutos entre los seis
     * intentos. Reintentar cada minuto contra un servidor caido no lo levanta;
     * solo lo golpea mas.
     *
     * El numero de intento se pasa y no se lee de la fila a proposito: quien
     * llama acaba de fallar y todavia no ha guardado el contador, asi que leer
     * `$this->attempts` daria la espera del intento anterior.
     */
    public function backoffSeconds(int $attempt): int
    {
        return 60 * (2 ** max($attempt - 1, 0));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'delivered_at' => 'immutable_datetime',
            'next_attempt_at' => 'immutable_datetime',
        ];
    }
}
