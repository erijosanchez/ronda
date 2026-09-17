<?php

declare(strict_types=1);

namespace Ronda\Notifications\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use Ronda\Identity\Domain\Models\User;
use Ronda\Notifications\Application\Data\NotificationMessage;
use Ronda\Notifications\Domain\NotificationTopic;

/**
 * Manda un aviso solo la primera vez. RONDA-PLAN-MAESTRO.md sec. 9.4
 *
 * El repaso de SLA corre cada hora sobre las mismas obligaciones y envios. Sin
 * esto, una entrega incumplida el lunes avisaria otra vez cada hora hasta que
 * alguien la justificara.
 *
 * La garantia es la restriccion unica de `sla_events` (asunto, tema, nivel), no
 * una consulta previa: dos pasadas simultaneas del job no pueden colarse entre
 * la comprobacion y la escritura. Se anota ANTES de mandar; si el envio falla,
 * el aviso se pierde en vez de repetirse en bucle, que es el fallo que si
 * quema al usuario.
 */
final readonly class NotifyOnce
{
    public function __construct(
        private ConnectionInterface $connection,
        private SendNotification $send,
    ) {}

    /**
     * @param  list<User>  $recipients
     * @return bool si este aviso se mando ahora (false: ya estaba anotado)
     */
    public function __invoke(
        Model $subject,
        NotificationTopic $topic,
        int $level,
        array $recipients,
        NotificationMessage $message,
        ?CarbonImmutable $now = null,
    ): bool {
        $now ??= CarbonImmutable::now('UTC');

        $anotado = $this->connection->table('sla_events')->insertOrIgnore([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'topic' => $topic->value,
            'level' => $level,
            'recipients' => count($recipients),
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($anotado === 0) {
            return false;
        }

        ($this->send)($recipients, $message);

        return true;
    }
}
