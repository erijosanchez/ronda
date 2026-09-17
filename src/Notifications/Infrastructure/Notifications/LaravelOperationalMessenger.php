<?php

declare(strict_types=1);

namespace Ronda\Notifications\Infrastructure\Notifications;

use Illuminate\Contracts\Notifications\Dispatcher;
use Ronda\Identity\Domain\Models\User;
use Ronda\Notifications\Application\Contracts\OperationalMessenger;
use Ronda\Notifications\Application\Data\NotificationMessage;

/**
 * Entrega los avisos con el sistema de notificaciones de Laravel.
 *
 * Es el unico punto donde la aplicacion toca el framework de notificaciones.
 * Anadir WhatsApp es anadir un canal en `config/notifications.php` y su driver:
 * ni las Actions ni los oyentes se enteran.
 */
final readonly class LaravelOperationalMessenger implements OperationalMessenger
{
    public function __construct(
        private Dispatcher $notifications,
    ) {}

    /**
     * @param  list<User>  $recipients
     */
    public function send(array $recipients, NotificationMessage $message): void
    {
        $this->notifications->send($recipients, new OperationalNotification($message));
    }
}
