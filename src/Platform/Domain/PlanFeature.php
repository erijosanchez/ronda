<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain;

/**
 * Funciones que un plan enciende o apaga. RONDA-PLAN-MAESTRO.md sec. 3.6
 *
 * Se resuelven con `laravel/pennant` contra el plan del cliente, asi que
 * quien las consulta no sabe —ni tiene por que— de que plan salen.
 *
 * Tres de las cuatro gobiernan trabajo que todavia no existe (WhatsApp es
 * fase 1 bloqueada por el tramite con Meta, la API es fase 3, los flujos
 * propios y el SSO estan sin empezar). Se declaran igual: cuando ese codigo
 * llegue, preguntara por su bandera en vez de nacer encendido para todos y
 * tener que apagarlo despues.
 */
enum PlanFeature: string
{
    /** Avisos por WhatsApp, ademas de correo y campana. */
    case WhatsApp = 'whatsapp';

    /** API publica v1 y webhooks salientes. */
    case Api = 'api';

    /** Flujos de revision configurables por el cliente. */
    case CustomWorkflows = 'custom_workflows';

    /** Inicio de sesion con el proveedor de identidad del cliente. */
    case SingleSignOn = 'sso';

    public function label(): string
    {
        return __('plan-features.'.$this->value);
    }
}
