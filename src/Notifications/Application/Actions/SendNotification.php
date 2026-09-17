<?php

declare(strict_types=1);

namespace Ronda\Notifications\Application\Actions;

use Ronda\Identity\Domain\Models\User;
use Ronda\Notifications\Application\Contracts\OperationalMessenger;
use Ronda\Notifications\Application\Data\NotificationMessage;

/**
 * Manda un aviso a unas personas. RONDA-PLAN-MAESTRO.md sec. 9.3
 *
 * Es el unico sitio por el que sale un aviso. Los canales los decide la
 * configuracion del tema, asi que aqui no hay ni correo ni campana: hay un
 * mensaje y unos destinatarios.
 *
 * @see NotifyOnce para lo que no debe repetirse (recordatorios y escalamiento).
 */
final readonly class SendNotification
{
    public function __construct(
        private OperationalMessenger $messenger,
    ) {}

    /**
     * @param  list<User>  $recipients
     * @return int cuantos avisos se mandaron
     */
    public function __invoke(array $recipients, NotificationMessage $message): int
    {
        if ($recipients === []) {
            return 0;
        }

        $this->messenger->send($recipients, $message);

        return count($recipients);
    }
}
