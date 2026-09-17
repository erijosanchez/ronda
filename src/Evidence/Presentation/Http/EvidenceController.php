<?php

declare(strict_types=1);

namespace Ronda\Evidence\Presentation\Http;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Routing\Controller;
use Ronda\Evidence\Domain\Models\Attachment;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sirve un archivo de evidencia por su URL firmada. ADR 0009.
 *
 * Tres barreras, en este orden:
 *
 *   1. `signed` (en la ruta): la URL la emitio esta aplicacion para ESTE
 *      dominio de tenant y no ha caducado.
 *   2. `auth` (en el grupo): hay sesion. Una URL firmada reenviada por WhatsApp
 *      no sirve a quien no ha entrado.
 *   3. La Policy, otra vez: la firma dice que alguien pudo verlo hace unos
 *      minutos, no que quien la presenta pueda verlo ahora.
 *
 * El binding busca el adjunto en la base del tenant actual: un id de otro
 * cliente simplemente no existe aqui.
 */
final class EvidenceController extends Controller
{
    public function __invoke(Attachment $attachment, FilesystemManager $filesystem, Gate $gate): StreamedResponse
    {
        $gate->authorize('view', $attachment);

        $stream = $filesystem->disk($attachment->disk)->readStream($attachment->path);

        abort_if($stream === null, 404);

        // Inline solo lo que es imagen (ya saneada al guardarse). Un PDF o una
        // hoja de calculo se descargan: servir documentos inline en el dominio
        // de la aplicacion es invitar a que se ejecuten en su origen.
        $disposicion = $attachment->isImage() ? 'inline' : 'attachment';

        return response()->stream(static function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $attachment->mime_type,
            'Content-Length' => (string) $attachment->bytes,
            'Content-Disposition' => sprintf('%s; filename="%s"', $disposicion, addcslashes($attachment->original_name, '"\\')),
            'X-Content-Type-Options' => 'nosniff',
            // Evidencia privada: ni el navegador ni un proxy la guardan.
            'Cache-Control' => 'private, no-store, max-age=0',
            // El SHA-256 viaja con el archivo para poder verificarlo al bajarlo.
            'X-Evidence-SHA256' => $attachment->sha256,
        ]);
    }
}
