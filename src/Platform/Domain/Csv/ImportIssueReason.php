<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Csv;

/**
 * Por que una fila de un CSV no se puede importar.
 * RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * Es un motivo y no un texto porque el mensaje que lee el usuario lo arma la
 * pantalla con `__()` (CLAUDE.md, regla 10), y porque el mismo motivo se
 * cuenta y se agrupa: «faltan 14 correos» se lee mejor que catorce frases.
 */
enum ImportIssueReason: string
{
    /** La columna es obligatoria y viene vacia. */
    case Required = 'required';

    /** Tiene algo, pero no con la forma que hace falta. */
    case Invalid = 'invalid';

    /** Se pasa del largo que admite la columna. */
    case TooLong = 'too_long';

    /** El mismo valor aparece dos veces DENTRO del archivo. */
    case Duplicated = 'duplicated';

    /** Apunta a algo que todavia no existe en Ronda: una zona, una sede. */
    case NotFound = 'not_found';

    /** El valor ya lo usa algo que esta importacion no puede tocar. */
    case Taken = 'taken';

    /** Es un valor valido, pero concederlo desde un CSV no lo es. */
    case NotAllowed = 'not_allowed';
}
