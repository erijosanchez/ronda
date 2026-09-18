<?php

declare(strict_types=1);

namespace Ronda\Insights\Presentation\Http;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Routing\Controller;
use Ronda\Insights\Domain\Models\Export;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga el archivo de una exportacion. RONDA-PLAN-MAESTRO.md sec. 13
 *
 * El archivo vive en el bucket privado y sale por aqui, nunca por una URL del
 * bucket. No lleva firma como la evidencia porque no se comparte: solo lo baja
 * quien lo pidio, y la Policy lo comprueba en cada descarga.
 */
final class ExportDownloadController extends Controller
{
    public function __invoke(Export $export, FilesystemManager $filesystem, Gate $gate): StreamedResponse
    {
        $gate->authorize('download', $export);

        $stream = $filesystem->disk((string) $export->disk)->readStream((string) $export->path);

        abort_if($stream === null, 404);

        return response()->stream(static function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Length' => (string) $export->bytes,
            'Content-Disposition' => sprintf('attachment; filename="%s"', addcslashes((string) $export->file_name, '"\\')),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
