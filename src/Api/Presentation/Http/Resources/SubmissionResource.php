<?php

declare(strict_types=1);

namespace Ronda\Api\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Ronda\Submissions\Domain\Models\Submission;

/**
 * Un reporte entregado. RONDA-PLAN-MAESTRO.md sec. 13.1
 *
 * `data` son las respuestas tal como se entregaron, validadas contra la
 * VERSION de la plantilla que se uso (ADR 0012). Por eso viaja tambien
 * `template_version_id`: sin el, quien integra no puede saber contra que
 * esquema interpretar las claves.
 *
 * La evidencia no se incluye: son archivos privados que se sirven por URL
 * firmada y con su Policy (ADR 0009), no por esta respuesta.
 *
 * @mixin Submission
 */
final class SubmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'state' => (string) $this->state,
            'site_id' => $this->site_id,
            'template_id' => $this->template_id,
            'template_version_id' => $this->template_version_id,
            'obligation_id' => $this->obligation_id,
            'author_id' => $this->author_id,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'is_late' => (bool) $this->is_late,
            'minutes_late' => (int) $this->minutes_late,
            'data' => $this->data,
        ];
    }
}
