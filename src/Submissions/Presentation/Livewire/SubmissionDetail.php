<?php

declare(strict_types=1);

namespace Ronda\Submissions\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;
use Ronda\Evidence\Application\Queries\SignedEvidenceUrlQuery;
use Ronda\Evidence\Domain\Models\Attachment;
use Ronda\Forms\Domain\Models\TemplateVersion;
use Ronda\Identity\Domain\Models\User;
use Ronda\Submissions\Domain\Models\Submission;

/**
 * Un envio entregado, con su evidencia. RONDA-PLAN-MAESTRO.md sec. 9.5
 *
 * Se lee con la version del formulario CON LA QUE se respondio (ADR 0012), no
 * con la vigente: un arqueo de marzo se ve como se lleno en marzo.
 *
 * Cada archivo se muestra por una URL firmada de cinco minutos emitida en este
 * render, y solo si la Policy lo permite (ADR 0009). Si la pagina se queda
 * abierta mas tiempo, recargarla emite URLs nuevas.
 *
 * Es de solo lectura. Revisar, aprobar y rechazar llegan con Workflow.
 */
final class SubmissionDetail extends Component
{
    public Submission $submission;

    public function mount(Submission $submission): void
    {
        $this->authorize('view', $submission);

        $this->submission = $submission;
    }

    public function render(): View
    {
        $this->authorize('view', $this->submission);

        /** @var User $viewer */
        $viewer = auth()->user();
        $firmar = resolve(SignedEvidenceUrlQuery::class);

        /** @var Collection<string, Collection<int, Attachment>> $porCampo */
        $porCampo = Attachment::query()
            ->where('submission_id', $this->submission->getKey())
            ->orderBy('id')
            ->get()
            ->groupBy('field_key');

        $urls = [];

        foreach ($porCampo->flatten() as $attachment) {
            /** @var Attachment $attachment */
            if ($viewer->can('view', $attachment)) {
                $urls[$attachment->id] = $firmar($attachment, $viewer);
            }
        }

        $this->submission->loadMissing(['templateVersion.template', 'site', 'author']);

        $version = $this->submission->templateVersion;
        abort_unless($version instanceof TemplateVersion, 404);

        return view('submissions::show', [
            'fields' => $version->formSchema()->fields,
            'attachments' => $porCampo,
            'urls' => $urls,
        ]);
    }
}
