<?php

declare(strict_types=1);

namespace Ronda\Insights\Application\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Ronda\Identity\Domain\Models\User;
use Ronda\Insights\Application\Data\ExportRequest;
use Ronda\Insights\Application\Queries\SubmissionExportQuery;
use Ronda\Insights\Domain\ExportStatus;
use Ronda\Insights\Domain\Models\Export;
use Ronda\Notifications\Application\Actions\SendNotification;
use Ronda\Notifications\Application\Data\NotificationMessage;
use Ronda\Notifications\Application\Queries\TenantUrlQuery;
use Ronda\Notifications\Domain\NotificationTopic;
use RuntimeException;
use Throwable;

/**
 * Escribe el archivo de una exportacion. RONDA-PLAN-MAESTRO.md sec. 13
 *
 * En streaming de punta a punta: la consulta se recorre por lotes y openspout
 * va escribiendo el XLSX en disco segun llegan las filas. En ningun momento
 * estan las 50 000 filas en memoria.
 *
 * Se escribe primero a un archivo temporal del contenedor y solo al final se
 * sube al bucket: un archivo a medias en el bucket parece una exportacion
 * terminada.
 *
 * Al terminar avisa a quien lo pidio (Notifications). Si falla, deja el motivo
 * en la fila y tambien avisa: una exportacion que nunca llega y nadie explica
 * es peor que un error.
 */
final class GenerateSubmissionExportJob implements ShouldQueue
{
    use Queueable;

    /** Minutos que puede tardar antes de darla por perdida. */
    public int $timeout = 900;

    public function __construct(
        private readonly int $exportId,
    ) {}

    public function handle(
        SubmissionExportQuery $query,
        FilesystemManager $filesystem,
        SendNotification $send,
        TenantUrlQuery $url,
    ): void {
        $export = Export::query()->find($this->exportId);

        if (! $export instanceof Export || $export->status !== ExportStatus::Queued) {
            return;
        }

        $export->forceFill(['status' => ExportStatus::Processing->value])->save();

        try {
            [$ruta, $nombre, $filas, $bytes] = $this->write($export, $query, $filesystem);

            $export->forceFill([
                'status' => ExportStatus::Completed->value,
                'disk' => 'evidence',
                'path' => $ruta,
                'file_name' => $nombre,
                'rows' => $filas,
                'bytes' => $bytes,
                'completed_at' => CarbonImmutable::now('UTC'),
            ])->save();
        } catch (Throwable $e) {
            $export->forceFill([
                'status' => ExportStatus::Failed->value,
                'error' => mb_substr($e->getMessage(), 0, 500),
                'completed_at' => CarbonImmutable::now('UTC'),
            ])->save();

            $this->notify($export, $send, $url);

            throw $e;
        }

        $this->notify($export, $send, $url);
    }

    /**
     * @return array{0: string, 1: string, 2: int, 3: int}
     */
    private function write(Export $export, SubmissionExportQuery $query, FilesystemManager $filesystem): array
    {
        $request = ExportRequest::fromArray($export->filters);

        // El archivo se arma en el disco privado del tenant y no en /tmp con
        // `tempnam`: un nombre predecible en un directorio compartido es una
        // carrera esperando a pasar (lo veta el preset de seguridad de Pest).
        $local = $filesystem->disk('local');
        $relativo = 'exports/'.mb_strtolower((string) Str::ulid()).'.xlsx';
        $local->makeDirectory('exports');
        $temporal = $local->path($relativo);

        $writer = new Writer;
        $writer->openToFile($temporal);
        $writer->addRow(Row::fromValues($query->headings($request)));

        $filas = 0;

        foreach ($query->rows($request) as $fila) {
            $writer->addRow(Row::fromValues($fila));
            $filas++;
        }

        $writer->close();

        $tenant = tenant();
        $nombre = sprintf('envios-%s-a-%s.xlsx', $request->from, $request->to);
        $ruta = sprintf(
            'tenants/%s/exports/%s.xlsx',
            $tenant?->getTenantKey() ?? 'sin-tenant',
            mb_strtolower((string) Str::ulid()),
        );

        $bytes = (int) $local->size($relativo);
        $manejador = $local->readStream($relativo);

        if ($manejador === null) {
            $local->delete($relativo);

            throw new RuntimeException('No se pudo leer el archivo temporal de la exportacion.');
        }

        try {
            // Al bucket solo al final: un archivo a medias alli parece una
            // exportacion terminada.
            $filesystem->disk('evidence')->writeStream($ruta, $manejador);
        } finally {
            if (is_resource($manejador)) {
                fclose($manejador);
            }

            $local->delete($relativo);
        }

        return [$ruta, $nombre, $filas, $bytes];
    }

    private function notify(Export $export, SendNotification $send, TenantUrlQuery $url): void
    {
        $quien = User::query()->find($export->requested_by);

        if (! $quien instanceof User) {
            return;
        }

        $listo = $export->status === ExportStatus::Completed;

        $send([$quien], new NotificationMessage(
            topic: NotificationTopic::ExportReady,
            title: $listo ? __('Your export is ready') : __('Your export failed'),
            body: $listo
                ? __('The file with :count row(s) is ready to download.', ['count' => (int) $export->rows])
                : __('The export could not be generated: :reason', ['reason' => (string) $export->error]),
            url: $url('exports.index'),
            meta: ['export_id' => $export->id],
        ));
    }
}
