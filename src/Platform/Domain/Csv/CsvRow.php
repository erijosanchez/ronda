<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Csv;

/**
 * Una fila de un CSV, ya normalizada. RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * Lleva su numero de linea en el archivo —no su posicion en la lista— porque
 * es el unico dato con el que quien importa puede encontrar el error: «fila 7»
 * tiene que ser la fila 7 de su hoja de calculo, contando la cabecera.
 */
final readonly class CsvRow
{
    /**
     * @param  array<string, string>  $values  indexados por cabecera normalizada
     */
    public function __construct(
        public int $number,
        private array $values,
    ) {}

    public function get(string $column): string
    {
        return trim($this->values[$column] ?? '');
    }

    public function has(string $column): bool
    {
        return $this->get($column) !== '';
    }

    /**
     * Si la fila esta vacia del todo.
     *
     * Una hoja de calculo guardada desde Excel suele traer filas en blanco al
     * final. Tratarlas como error llenaria el informe de ruido por algo que el
     * usuario no escribio.
     */
    public function isBlank(): bool
    {
        return array_all($this->values, static fn (string $valor): bool => trim($valor) === '');
    }
}
