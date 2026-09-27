<?php

declare(strict_types=1);

namespace Ronda\Api\Domain\Exceptions;

use DomainException;

/**
 * La direccion de un webhook no se puede aceptar.
 * RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * El mensaje va en ingles porque es para los registros y para la columna
 * `error` del registro de entregas, no para nadie: lo que lee quien configura
 * el webhook lo arma la pantalla con `__()` a partir de `reason` y `value`
 * (CLAUDE.md, regla 10).
 */
final class UnsafeWebhookUrl extends DomainException
{
    private function __construct(
        public readonly string $value,
        public readonly WebhookUrlRejection $reason,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function malformed(string $url): self
    {
        return new self($url, WebhookUrlRejection::Malformed, "«{$url}» is not a URL.");
    }

    public static function insecureScheme(string $scheme): self
    {
        return new self(
            $scheme,
            WebhookUrlRejection::InsecureScheme,
            "«{$scheme}» is not allowed: webhooks go over https.",
        );
    }

    public static function privateHost(string $host): self
    {
        return new self(
            $host,
            WebhookUrlRejection::PrivateHost,
            "«{$host}» points inside a private network.",
        );
    }
}
