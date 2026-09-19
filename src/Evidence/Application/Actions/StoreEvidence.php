<?php

declare(strict_types=1);

namespace Ronda\Evidence\Application\Actions;

use finfo;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Evidence\Application\Data\EvidenceUpload;
use Ronda\Evidence\Domain\EvidenceKind;
use Ronda\Evidence\Domain\Exceptions\InvalidEvidence;
use Ronda\Evidence\Domain\Models\Attachment;
use Ronda\Evidence\Domain\Services\ExifReader;
use Ronda\Evidence\Domain\Services\ImageSanitizer;
use Ronda\Evidence\Domain\ValueObjects\GeoPoint;
use Ronda\Evidence\Domain\ValueObjects\ImageMetadata;
use Ronda\Identity\Domain\Models\User;
use Ronda\Platform\Domain\Contracts\PlanProvider;
use Ronda\Platform\Domain\Exceptions\PlanLimitExceeded;
use Ronda\Submissions\Domain\Models\Submission;
use Throwable;

/**
 * Valida, sanea y guarda un archivo de evidencia de un envio.
 * RONDA-PLAN-MAESTRO.md sec. 9.5, 10.4 y ADR 0009.
 *
 * En este orden, y cada paso por una razon:
 *
 *   1. Tamano y tipo por CONTENIDO, contra la lista blanca de su clase.
 *   2. De una imagen, EXIF (fecha y coordenadas) ANTES de sanearla, porque
 *      sanear lo borra.
 *   3. Sanear: la imagen se reescribe desde sus pixeles (ImageSanitizer).
 *   4. SHA-256 de lo que se va a guardar, no de lo que llego.
 *   5. Guardar en el bucket privado bajo el prefijo del tenant, con un nombre
 *      que no sale del usuario.
 *   6. La fila. Si falla, se borra el objeto: no queda un archivo sin dueno.
 *
 * No abre transaccion: la abre SubmitReport, que es quien sabe que el envio y
 * su evidencia van juntos. Si la transaccion se deshace despues, SubmitReport
 * llama a DiscardEvidence con las rutas escritas.
 */
final readonly class StoreEvidence
{
    public const string DISK = 'evidence';

    private const array EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];

    public function __construct(
        private FilesystemManager $filesystem,
        private ExifReader $exif,
        private ImageSanitizer $sanitizer,
        private PlanProvider $plan,
    ) {}

    public function __invoke(
        Submission $submission,
        string $fieldKey,
        EvidenceKind $kind,
        EvidenceUpload $upload,
        User $author,
    ): Attachment {
        $mime = $this->guardContent($upload->contents, $kind);

        $site = Site::query()->withoutGlobalScopes()->find($submission->site_id);

        $metadata = $kind->isImage($mime) ? $this->exif->read($upload->contents, $mime) : ImageMetadata::empty();

        $contenido = $kind->isImage($mime)
            ? $this->sanitizer->sanitize($upload->contents, $mime, $metadata->orientation)
            : $upload->contents;

        // Antes de escribir nada en el bucket: rechazar despues de subir
        // dejaria el archivo ocupando espacio que el plan ya no cubre.
        $this->guardStorage((int) $submission->site_id, strlen($contenido));

        [$ubicacion, $origen] = $this->location($metadata, $upload);

        $sede = GeoPoint::tryFromStrings($site?->latitude, $site?->longitude);

        $ruta = $this->path($mime);
        $disco = $this->filesystem->disk(self::DISK);
        $disco->put($ruta, $contenido);

        try {
            return Attachment::create([
                'submission_id' => $submission->getKey(),
                'field_key' => $fieldKey,
                'kind' => $kind->value,
                'disk' => self::DISK,
                'path' => $ruta,
                'original_name' => $this->safeName($upload->originalName),
                'mime_type' => $mime,
                'bytes' => strlen($contenido),
                'sha256' => hash('sha256', $contenido),
                'latitude' => $ubicacion?->latitudeAsString(),
                'longitude' => $ubicacion?->longitudeAsString(),
                'location_source' => $origen,
                'distance_meters' => $ubicacion instanceof GeoPoint && $sede instanceof GeoPoint
                    ? $ubicacion->distanceInMetersTo($sede)
                    : null,
                'captured_at' => $kind === EvidenceKind::Photo
                    ? $metadata->capturedAt($site->timezone ?? 'UTC')
                    : null,
                'uploaded_by' => $author->getKey(),
                'ip_address' => $upload->ipAddress,
            ]);
        } catch (Throwable $e) {
            $disco->delete($ruta);

            throw $e;
        }
    }

    /**
     * El espacio que el plan da POR SEDE (sec. 3.6): 1 GB en Starter, 5 en Pro.
     *
     * Se cuenta por sede y no por cliente porque asi se vende —el precio es por
     * sede activa—, y porque una sede que se pasa no puede dejar sin espacio a
     * las demas.
     *
     * Es una suma indexada por subida. Cuando el volumen lo pida, este numero
     * sale de `usage_metrics` en vez de recalcularse.
     *
     * @throws PlanLimitExceeded
     */
    private function guardStorage(int $siteId, int $incomingBytes): void
    {
        $limits = $this->plan->limits();

        if ($limits->storageGbPerSite === null) {
            return;
        }

        $usados = (int) Attachment::query()
            ->join('submissions', 'submissions.id', '=', 'attachments.submission_id')
            ->where('submissions.site_id', $siteId)
            ->sum('attachments.bytes');

        if (! $limits->allowsMoreStorage($usados, $incomingBytes)) {
            throw PlanLimitExceeded::storage($limits->storageGbPerSite);
        }
    }

    /**
     * @return string el tipo MIME detectado
     */
    private function guardContent(string $contents, EvidenceKind $kind): string
    {
        if ($contents === '') {
            throw InvalidEvidence::empty();
        }

        $maximo = (int) config('security.evidence.max_kilobytes', 10240);
        $kilobytes = (int) ceil(strlen($contents) / 1024);

        if ($kilobytes > $maximo) {
            throw InvalidEvidence::tooLarge($kilobytes, $maximo);
        }

        $mime = (string) new finfo(FILEINFO_MIME_TYPE)->buffer($contents);

        if (! in_array($mime, $kind->allowedMimeTypes(), true)) {
            throw $kind === EvidenceKind::Signature
                ? InvalidEvidence::invalidSignature()
                : InvalidEvidence::contentNotAllowed($mime);
        }

        return $mime;
    }

    /**
     * La camara manda sobre el navegador: el EXIF dice donde se TOMO la foto;
     * el dispositivo, donde estaba quien la entrego.
     *
     * @return array{0: GeoPoint|null, 1: string|null}
     */
    private function location(ImageMetadata $metadata, EvidenceUpload $upload): array
    {
        if ($metadata->location instanceof GeoPoint) {
            return [$metadata->location, 'exif'];
        }

        try {
            $dispositivo = GeoPoint::tryFromStrings($upload->deviceLatitude, $upload->deviceLongitude);
        } catch (InvalidEvidence) {
            $dispositivo = null;
        }

        return $dispositivo instanceof GeoPoint ? [$dispositivo, 'device'] : [null, null];
    }

    /**
     * `tenants/<id>/<anio>/<mes>/<ulid>.<ext>`.
     *
     * El prefijo del tenant se pone a mano y no se deja a un bootstrapper: un
     * listado del bucket tiene que poder separarse por cliente para la purga
     * (sec. 7) aunque la configuracion cambie. El nombre es un ULID, nunca el
     * del usuario: ni colisiona ni permite rutas manipuladas.
     */
    private function path(string $mime): string
    {
        $tenant = tenant();

        if ($tenant === null) {
            throw InvalidEvidence::outsideTenant();
        }

        return sprintf(
            'tenants/%s/%s/%s.%s',
            $tenant->getTenantKey(),
            now('UTC')->format('Y/m'),
            mb_strtolower((string) Str::ulid()),
            self::EXTENSIONS[$mime] ?? 'bin',
        );
    }

    /**
     * El nombre original solo se muestra. Se recorta y se quitan caracteres de
     * control y separadores de ruta.
     */
    private function safeName(string $name): string
    {
        $limpio = (string) preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '', basename(str_replace('\\', '/', $name)));

        return mb_substr(trim($limpio), 0, 200) ?: 'archivo';
    }
}
