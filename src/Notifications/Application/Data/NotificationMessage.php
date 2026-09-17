<?php

declare(strict_types=1);

namespace Ronda\Notifications\Application\Data;

use Ronda\Notifications\Domain\NotificationTopic;

/**
 * Lo que dice un aviso, ya resuelto. RONDA-PLAN-MAESTRO.md sec. 9.3
 *
 * El texto se arma donde se sabe de que se habla (la Action) y no dentro de la
 * notificacion: asi el mismo aviso vale para la campana y para el correo, y se
 * puede comprobar en una prueba sin mirar un correo.
 *
 * Los textos llegan ya traducidos. Guardar la clave y traducir al mostrar
 * dejaria la campana en el idioma de quien mire, no en el del aviso; hoy la
 * interfaz es solo en espanol (CLAUDE.md).
 */
final readonly class NotificationMessage
{
    /**
     * @param  string  $url  a donde lleva el aviso
     * @param  array<string, string|int|null>  $meta  datos sueltos para la campana
     */
    public function __construct(
        public NotificationTopic $topic,
        public string $title,
        public string $body,
        public string $url,
        public array $meta = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'topic' => $this->topic->value,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'meta' => $this->meta,
        ];
    }
}
