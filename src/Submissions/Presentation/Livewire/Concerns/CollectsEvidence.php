<?php

declare(strict_types=1);

namespace Ronda\Submissions\Presentation\Livewire\Concerns;

use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Ronda\Evidence\Application\Data\EvidenceUpload;

/**
 * Lo que un formulario de respuesta recoge del navegador ademas de las
 * respuestas: archivos, firmas dibujadas y la ubicacion del dispositivo.
 * RONDA-PLAN-MAESTRO.md sec. 9.5
 *
 * Lo comparten la entrega y la correccion. No valida contenido: eso lo hace
 * StoreEvidence, que no se fia de nada de lo que llega aqui.
 */
trait CollectsEvidence
{
    use WithFileUploads;

    /**
     * Fotos y archivos por clave de campo. Viven en la subida temporal de
     * Livewire (disco local del servidor) hasta que se entrega.
     *
     * @var array<string, array<int, TemporaryUploadedFile>>
     */
    public array $uploads = [];

    /**
     * Firmas como data URL PNG, por clave de campo. Las dibuja el componente
     * Alpine `signaturePad`.
     *
     * @var array<string, string>
     */
    public array $signatures = [];

    /** Ubicacion del dispositivo, si el navegador la dio. */
    public ?string $latitude = null;

    public ?string $longitude = null;

    /**
     * Primera barrera, barata: tamano y que sea un archivo.
     */
    public function updatedUploads(): void
    {
        $maximo = (int) config('security.evidence.max_kilobytes', 10240);

        $this->validate(['uploads.*.*' => ['file', 'max:'.$maximo]], [], ['uploads.*.*' => __('file')]);
    }

    public function removeUpload(string $key, int $index): void
    {
        $archivos = $this->uploads[$key] ?? [];
        unset($archivos[$index]);
        $this->uploads[$key] = array_values($archivos);
    }

    /**
     * Lo que llego del navegador, como EvidenceUpload por campo.
     *
     * @return array<string, list<EvidenceUpload>>
     */
    protected function collectedEvidence(): array
    {
        $ip = request()->ip();
        $porCampo = [];

        foreach ($this->uploads as $key => $archivos) {
            foreach ($archivos as $archivo) {
                if (! $archivo instanceof TemporaryUploadedFile) {
                    continue;
                }

                $porCampo[$key][] = new EvidenceUpload(
                    contents: (string) $archivo->get(),
                    originalName: $archivo->getClientOriginalName(),
                    deviceLatitude: $this->latitude,
                    deviceLongitude: $this->longitude,
                    ipAddress: $ip,
                );
            }
        }

        foreach ($this->signatures as $key => $dataUrl) {
            $png = $this->decodePng($dataUrl);

            if ($png === null) {
                continue;
            }

            $porCampo[$key][] = new EvidenceUpload(
                contents: $png,
                originalName: 'firma-'.$key.'.png',
                deviceLatitude: $this->latitude,
                deviceLongitude: $this->longitude,
                ipAddress: $ip,
            );
        }

        return $porCampo;
    }

    /**
     * El canvas entrega `data:image/png;base64,...`. Si no tiene esa forma no
     * se manda nada; que sea de verdad un PNG lo comprueba StoreEvidence.
     */
    private function decodePng(string $dataUrl): ?string
    {
        $prefijo = 'data:image/png;base64,';

        // Una firma dibujada pesa decenas de KB; 2 MB en base64 ya no es una firma.
        if (! str_starts_with($dataUrl, $prefijo) || strlen($dataUrl) > 2_800_000) {
            return null;
        }

        $bytes = base64_decode(mb_substr($dataUrl, mb_strlen($prefijo)), true);

        return $bytes === false || $bytes === '' ? null : $bytes;
    }
}
