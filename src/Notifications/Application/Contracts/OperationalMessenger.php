<?php

declare(strict_types=1);

namespace Ronda\Notifications\Application\Contracts;

use Ronda\Identity\Domain\Models\User;
use Ronda\Notifications\Application\Data\NotificationMessage;

/**
 * Por donde sale un aviso.
 *
 * La aplicacion decide QUE se avisa y a QUIEN; como llega (campana, correo, y
 * manana WhatsApp) es cosa de la infraestructura, que implementa esto. Por eso
 * la regla de dependencia: `Application` no conoce `Infrastructure`.
 */
interface OperationalMessenger
{
    /**
     * @param  list<User>  $recipients
     */
    public function send(array $recipients, NotificationMessage $message): void;
}
