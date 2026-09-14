<?php

declare(strict_types=1);

namespace Ronda\Forms\Domain;

/**
 * Estado de una plantilla. RONDA-PLAN-MAESTRO.md sec. 9.2
 *
 * Enum y no maquina de estados declarativa. El ADR 0007 aplica al flujo de los
 * envios, que tiene aprobadores, escalamiento y transiciones con guardas. Una
 * plantilla solo va de borrador a publicada y de ahi a archivada: montar el
 * paquete de estados para eso seria ceremonia sin invariante que proteger.
 * Anotado en el ADR 0012.
 */
enum TemplateStatus: string
{
    /** Nunca se ha publicado ninguna version. */
    case Draft = 'draft';

    case Published = 'published';

    /** Ya no se programa, pero sus envios siguen siendo legibles. */
    case Archived = 'archived';

    public function label(): string
    {
        return __('template-status.'.$this->value);
    }

    public function canBeScheduled(): bool
    {
        return $this === self::Published;
    }
}
