<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Exceptions;

/**
 * Por que un archivo no sirve como CSV. RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * Los cuatro motivos tienen arreglos distintos: volver a subir, llenar la
 * hoja, partirla en trozos o corregir la cabecera.
 */
enum CsvRejection: string
{
    case Unopenable = 'unopenable';

    case WithoutRows = 'without_rows';

    case TooManyRows = 'too_many_rows';

    case MissingColumns = 'missing_columns';
}
