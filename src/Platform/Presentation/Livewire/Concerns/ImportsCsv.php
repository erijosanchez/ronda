<?php

declare(strict_types=1);

namespace Ronda\Platform\Presentation\Livewire\Concerns;

use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Ronda\Platform\Domain\Contracts\CsvParser;
use Ronda\Platform\Domain\Csv\CsvTable;
use Ronda\Platform\Domain\Csv\ImportIssue;
use Ronda\Platform\Domain\Exceptions\CsvRejection;
use Ronda\Platform\Domain\Exceptions\UnreadableCsv;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lo que comparten las pantallas que importan un CSV.
 * RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * Un trait y no una clase base: el test de arquitectura exige que todas las
 * clases sean finales, y una clase final no se puede heredar.
 *
 * Aqui vive la parte que es igual para sedes y para personas —subir, leer,
 * traducir los problemas y ofrecer la plantilla— y nada de lo que cambia, que
 * es que significa cada columna.
 */
trait ImportsCsv
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $file = null;

    /** Resumen del analisis. Vacio hasta que se analiza. */
    public bool $analyzed = false;

    public int $toCreate = 0;

    public int $toUpdate = 0;

    /** @var list<array{row: int, message: string}> */
    public array $issues = [];

    public function updatedFile(): void
    {
        // Un archivo nuevo invalida el analisis anterior: confirmar lo de
        // antes con el archivo de ahora seria importar a ciegas.
        $this->reset('analyzed', 'toCreate', 'toUpdate', 'issues');
    }

    /**
     * El archivo subido, ya leido.
     *
     * Se vuelve a leer en cada paso —analizar y confirmar— a proposito: entre
     * uno y otro puede haber pasado un rato, y el plan se calcula contra lo
     * que hay en la base ahora, no contra lo que habia entonces.
     */
    protected function table(): ?CsvTable
    {
        $this->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        if (! $this->file instanceof TemporaryUploadedFile) {
            return null;
        }

        try {
            return resolve(CsvParser::class)->parse(
                $this->file->getRealPath(),
                $this->maxRows(),
            );
        } catch (UnreadableCsv $e) {
            $this->addError('file', $this->describeRejection($e));

            return null;
        }
    }

    /**
     * Tope de filas por archivo.
     *
     * Con mas, esto deja de ser una pantalla y pasa a ser un trabajo en cola,
     * que es otra conversacion. Decirlo es mejor que quedarse colgado.
     */
    protected function maxRows(): int
    {
        return 2000;
    }

    /**
     * @param  list<ImportIssue>  $issues
     * @return list<array{row: int, message: string}>
     */
    protected function describeIssues(array $issues): array
    {
        return array_map(fn (ImportIssue $issue): array => [
            'row' => $issue->row,
            'message' => __('import.issues.'.$issue->reason->value, [
                'column' => __('import.columns.'.$issue->column),
                'value' => $issue->value,
            ]),
        ], $issues);
    }

    protected function describeRejection(UnreadableCsv $e): string
    {
        return match ($e->reason) {
            CsvRejection::Unopenable => __('The file could not be read. Try uploading it again.'),
            CsvRejection::WithoutRows => __('The file has no rows.'),
            CsvRejection::TooManyRows => __('The file has more than :limit rows. Split it into smaller files.', ['limit' => $e->limit]),
            CsvRejection::MissingColumns => __('The file is missing these columns: :columns.', [
                'columns' => implode(', ', array_map(
                    static fn (string $columna): string => __('import.columns.'.$columna),
                    $e->columns,
                )),
            ]),
        };
    }

    /**
     * La plantilla, para que nadie tenga que adivinar las cabeceras.
     *
     * Con BOM: sin el, Excel abre el archivo en Windows-1252 y la primera
     * tilde que vea la ensena rota, que es justo la impresion que no interesa
     * dar en el primer archivo que toca el cliente.
     *
     * Se devuelve como descarga en streaming y no como respuesta normal
     * porque es lo unico que Livewire reconoce como archivo: una `Response` a
     * secas se descarta en silencio y el boton no hace nada.
     *
     * @param  list<string>  $headers
     * @param  list<string>  $example
     */
    protected function csvTemplate(string $name, array $headers, array $example): StreamedResponse
    {
        $contenido = "\u{FEFF}".implode(';', $headers)."\r\n".implode(';', $example)."\r\n";

        return response()->streamDownload(
            static function () use ($contenido): void {
                echo $contenido;
            },
            $name,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
