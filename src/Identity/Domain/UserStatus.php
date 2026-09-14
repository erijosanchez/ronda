<?php

declare(strict_types=1);

namespace Ronda\Identity\Domain;

/**
 * Estado de una persona dentro del cliente. RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * Suspender no es borrar: la persona deja de entrar pero su historial de
 * envios y revisiones sigue en pie, que es lo que hace auditable el sistema.
 */
enum UserStatus: string
{
    case Active = 'active';

    case Suspended = 'suspended';

    public function label(): string
    {
        return __('user-status.'.$this->value);
    }

    public function canSignIn(): bool
    {
        return $this === self::Active;
    }
}
