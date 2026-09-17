<?php

declare(strict_types=1);

namespace Ronda\Submissions\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Ronda\Evidence\Application\Data\EvidenceUpload;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Forms\Domain\ValueObjects\Field;
use Ronda\Forms\Domain\ValueObjects\FormSchema;
use Ronda\Forms\Domain\ValueObjects\VisibilityCondition;
use Ronda\Identity\Domain\Models\User;
use Ronda\Scheduling\Domain\Models\Obligation;
use Ronda\Submissions\Application\Actions\SubmitReport;
use Ronda\Submissions\Domain\Exceptions\CannotSubmit;
use Ronda\Submissions\Domain\Exceptions\InvalidAnswers;

/**
 * Rellenar y entregar un reporte. RONDA-PLAN-MAESTRO.md sec. 9.1 y 9.5
 *
 * Valida, invoca la Action y devuelve (regla 1). La validacion de verdad es la
 * del dominio (AnswerValidator y StoreEvidence): esta pantalla solo traduce sus
 * errores a cada campo y convierte lo que llega del navegador en EvidenceUpload.
 *
 * Las condiciones de visibilidad se evaluan en el servidor en cada render, con
 * las respuestas del momento. El unico JavaScript es el del bundle (firma y
 * ubicacion, en resources/js/evidence.js): la CSP no admite scripts en linea
 * (ADR 0011).
 */
final class SubmissionForm extends Component
{
    use WithFileUploads;

    public Obligation $obligation;

    /**
     * Respuestas en curso, por clave de campo.
     *
     * @var array<string, mixed>
     */
    public array $answers = [];

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

    /** Ubicacion del dispositivo al entregar, si el navegador la dio. */
    public ?string $latitude = null;

    public ?string $longitude = null;

    public function mount(Obligation $obligation): void
    {
        $this->authorize('submit', $obligation);

        $this->obligation = $obligation;
    }

    /**
     * Primera barrera, barata: tamano y que sea un archivo. El contenido real lo
     * decide StoreEvidence, que no se fia de esto.
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

    public function submit(): void
    {
        $this->authorize('submit', $this->obligation);

        /** @var User $author */
        $author = auth()->user();

        try {
            $submission = resolve(SubmitReport::class)(
                $this->obligation,
                $author,
                $this->answers,
                evidence: $this->evidence(),
            );
        } catch (InvalidAnswers $e) {
            foreach ($e->errors as $key => $message) {
                $this->addError("answers.{$key}", $message);
            }

            return;
        } catch (CannotSubmit $e) {
            $this->addError('obligation', $e->getMessage());

            return;
        }

        session()->flash('status', __('Report submitted.'));
        $this->redirectRoute('submissions.show', ['submission' => $submission], navigate: true);
    }

    public function render(): View
    {
        $schema = $this->schema();

        return view('submissions::form', [
            'fields' => $schema instanceof FormSchema ? array_values(array_filter(
                $schema->fields,
                fn (Field $field): bool => ! $field->visibleWhen instanceof VisibilityCondition
                    || $field->visibleWhen->isSatisfiedBy($this->answers),
            )) : [],
            'site' => $this->obligation->site,
            'template' => Template::query()->find($this->obligation->template_id),
        ]);
    }

    /**
     * Lo que llego del navegador, como EvidenceUpload por campo.
     *
     * @return array<string, list<EvidenceUpload>>
     */
    private function evidence(): array
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

    private function schema(): ?FormSchema
    {
        $template = Template::query()->with('currentVersion')->find($this->obligation->template_id);

        return $template?->currentVersion?->formSchema();
    }
}
