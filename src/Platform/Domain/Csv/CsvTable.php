<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Csv;

/**
 * Un CSV leido: cabeceras y filas. RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * Es el punto en el que deja de importar de donde vino el archivo —subido,
 * pegado o de una prueba— y empieza a importar lo que dice.
 */
final readonly class CsvTable
{
    /**
     * @param  list<string>  $headers  ya normalizadas: sin acentos, en minusculas
     * @param  list<CsvRow>  $rows
     */
    public function __construct(
        public array $headers,
        public array $rows,
    ) {}

    /**
     * Las columnas obligatorias que el archivo no trae.
     *
     * Se devuelven todas juntas y no la primera que falta: quien exporto mal
     * su hoja prefiere arreglarla de una vez a descubrir las columnas de una
     * en una.
     *
     * @param  list<string>  $columns
     * @return list<string>
     */
    public function missing(array $columns): array
    {
        return array_values(array_filter(
            $columns,
            fn (string $columna): bool => ! in_array($columna, $this->headers, true),
        ));
    }

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    public function count(): int
    {
        return count($this->rows);
    }
}
