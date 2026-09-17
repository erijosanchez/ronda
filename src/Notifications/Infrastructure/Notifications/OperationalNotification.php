<?php

declare(strict_types=1);

namespace Ronda\Notifications\Infrastructure\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Ronda\Notifications\Application\Data\NotificationMessage;

/**
 * El unico aviso operativo de Ronda. RONDA-PLAN-MAESTRO.md sec. 9.3 y 9.4
 *
 * Una sola clase parametrizada por tema y no una por aviso: todas dirian lo
 * mismo con otro texto, y el texto ya viene resuelto en el mensaje. Los canales
 * los decide la configuracion (`config/notifications.php`), asi que anadir
 * WhatsApp no toca este archivo.
 *
 * Va en cola: un repaso de SLA que avisa a cincuenta personas no puede quedarse
 * esperando al servidor de correo.
 */
final class OperationalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly NotificationMessage $message,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        /** @var list<string> $canales */
        $canales = config('notifications.channels.'.$this->message->topic->value, ['database']);

        return $canales;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->message->toArray();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->message->title)
            ->greeting(__('Hello'))
            ->line($this->message->body)
            ->action(__('Open in Ronda'), $this->message->url);
    }
}
