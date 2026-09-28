<?php

declare(strict_types=1);

namespace Ronda\Platform\Infrastructure\Csv;

use Ronda\Platform\Domain\Contracts\CsvParser;
use Ronda\Platform\Domain\Csv\CsvRow;
use Ronda\Platform\Domain\Csv\CsvTable;
use Ronda\Platform\Domain\Exceptions\UnreadableCsv;

/**
 * Lee un CSV del mundo real. RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * «Del mundo real» quiere decir el que sale de Excel en una oficina de Lima, y
 * eso obliga a cuatro cosas que un `fgetcsv` pelado no hace:
 *
 *   1. SEPARADOR. Excel en espanol guarda con punto y coma, no con coma. Un
 *      lector que solo entienda comas ve una sola columna gigante y le dice al
 *      usuario que le faltan todas las columnas, que es lo mas parecido a
 *      mentirle.
 *   2. CODIFICACION. «Guardar como CSV» produce Windows-1252, no UTF-8: sin
 *      convertirlo, «Miraflores» entra bien y «Ancón» entra roto y ademas
 *      rompe el JSON de la respuesta.
 *   3. BOM. Excel antepone tres bytes invisibles a la primera cabecera, asi
 *      que `codigo` deja de llamarse `codigo` y la columna «no existe».
 *   4. CABECERAS. `Código`, `CODIGO` y ` codigo ` son la misma columna para
 *      cualquier persona; tienen que serlo tambien aqui.
 *
 * Se lee en streaming y con un tope de filas: un archivo de medio millon de
 * lineas no puede tumbar el proceso, y es mejor decir «partelo» que morir
 * intentandolo.
 */
final readonly class StreamingCsvParser implements CsvParser
{
    /** Separadores que se prueban, en orden de probabilidad aqui. */
    private const array DELIMITERS = [';', ',', "\t", '|'];

    public function parse(string $path, int $maxRows): CsvTable
    {
        $manejador = @fopen($path, 'rb');

        if ($manejador === false) {
            throw UnreadableCsv::unopenable($path);
        }

        try {
            $primera = fgets($manejador);

            if ($primera === false) {
                throw UnreadableCsv::withoutRows();
            }

            $separador = $this->delimiter($this->clean($primera));

            rewind($manejador);

            $cabeceras = $this->headers($manejador, $separador);
            $filas = $this->rows($manejador, $separador, $cabeceras, $maxRows);
        } finally {
            fclose($manejador);
        }

        return new CsvTable($cabeceras, $filas);
    }

    /**
     * @return list<string>
     */
    private function headers(mixed $handle, string $delimiter): array
    {
        $cabecera = fgetcsv($handle, null, $delimiter, '"', '');

        if ($cabecera === false || $cabecera === [null]) {
            throw UnreadableCsv::withoutRows();
        }

        return array_values(array_map(
            fn (?string $valor): string => $this->normalize($this->clean((string) $valor)),
            $cabecera,
        ));
    }

    /**
     * @param  list<string>  $headers
     * @return list<CsvRow>
     */
    private function rows(mixed $handle, string $delimiter, array $headers, int $maxRows): array
    {
        $filas = [];
        $linea = 1;

        while (($valores = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $linea++;

            if ($valores === [null]) {
                continue;
            }

            $fila = new CsvRow($linea, $this->combine($headers, $valores));

            // Las hojas de calculo arrastran filas en blanco al final. Contarlas
            // como error llenaria el informe de ruido por algo que nadie
            // escribio.
            if ($fila->isBlank()) {
                continue;
            }

            if (count($filas) >= $maxRows) {
                throw UnreadableCsv::tooManyRows($maxRows);
            }

            $filas[] = $fila;
        }

        if ($filas === []) {
            throw UnreadableCsv::withoutRows();
        }

        return $filas;
    }

    /**
     * Empareja valores con cabeceras, sobren o falten.
     *
     * Una fila con menos celdas que la cabecera es normal cuando las ultimas
     * columnas van vacias; rechazarla por eso seria pedantería.
     *
     * @param  list<string>  $headers
     * @param  list<string|null>  $values
     * @return array<string, string>
     */
    private function combine(array $headers, array $values): array
    {
        $fila = [];

        foreach ($headers as $posicion => $cabecera) {
            $fila[$cabecera] = $this->clean((string) ($values[$posicion] ?? ''));
        }

        return $fila;
    }

    /**
     * El separador de la cabecera: el que produce mas columnas.
     *
     * Se mira la cabecera y no una fila cualquiera porque es la unica linea
     * que seguro tiene todas las columnas.
     */
    private function delimiter(string $header): string
    {
        $mejor = self::DELIMITERS[0];
        $columnas = 0;

        foreach (self::DELIMITERS as $candidato) {
            $cuantas = count(str_getcsv($header, $candidato, '"', ''));

            if ($cuantas > $columnas) {
                $columnas = $cuantas;
                $mejor = $candidato;
            }
        }

        return $mejor;
    }

    /**
     * Deja el texto en UTF-8 y sin el BOM de Excel.
     */
    private function clean(string $value): string
    {
        $limpio = str_replace("\u{FEFF}", '', $value);

        if (! mb_check_encoding($limpio, 'UTF-8')) {
            // Lo que sale de «Guardar como CSV» en Windows. Se convierte en vez
            // de rechazarlo: el usuario no eligio la codificacion, se la puso
            // su programa.
            $limpio = mb_convert_encoding($limpio, 'UTF-8', 'Windows-1252');
        }

        return rtrim($limpio, "\r\n");
    }

    /**
     * `Código` y ` CODIGO ` son la misma columna.
     */
    private function normalize(string $header): string
    {
        $sinAcentos = strtr(mb_strtolower(trim($header)), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n',
        ]);

        return (string) preg_replace('/[^a-z0-9]+/', '_', $sinAcentos);
    }
}
