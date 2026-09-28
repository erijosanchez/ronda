<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Exceptions;

use DomainException;

/**
 * El archivo no se puede leer como CSV. RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * Es distinto de «el archivo tiene filas mal»: eso se cuenta fila a fila con
 * ImportIssue. Esto es que no hay nada que contar.
 *
 * El mensaje va en ingles porque es para los registros; el que lee quien
 * importa lo arma la pantalla con `__()` a partir de `reason` y `columns`
 * (CLAUDE.md, regla 10).
 */
final class UnreadableCsv extends DomainException
{
    /**
     * @param  list<string>  $columns
     */
    private function __construct(
        public readonly CsvRejection $reason,
        public readonly array $columns,
        public readonly int $limit,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function unopenable(string $path): self
    {
        return new self(CsvRejection::Unopenable, [], 0, "«{$path}» could not be opened.");
    }

    public static function withoutRows(): self
    {
        return new self(CsvRejection::WithoutRows, [], 0, 'The file has no rows.');
    }

    public static function tooManyRows(int $limit): self
    {
        return new self(CsvRejection::TooManyRows, [], $limit, "The file has more than {$limit} rows.");
    }

    /**
     * @param  list<string>  $columns
     */
    public static function missingColumns(array $columns): self
    {
        return new self(
            CsvRejection::MissingColumns,
            $columns,
            0,
            'The file is missing columns: '.implode(', ', $columns).'.',
        );
    }
}
