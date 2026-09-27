<?php

declare(strict_types=1);

namespace Ronda\Api\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Ronda\Scheduling\Domain\Models\Obligation;

/**
 * Una obligacion: lo que una sede debe entregar un dia concreto.
 * RONDA-PLAN-MAESTRO.md sec. 13.1 y ADR 0008
 *
 * Las horas salen en ISO 8601 con zona: la ventana de una sede de Arequipa y
 * la de una de Lima no son la misma hora aunque el reloj diga lo mismo, y una
 * integracion que reciba «08:00» a secas no tiene forma de saberlo.
 *
 * @mixin Obligation
 */
final class ObligationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => (string) $this->status,
            'occurrence_date' => $this->occurrence_date?->toDateString(),
            'opens_at' => $this->opens_at?->toIso8601String(),
            'due_at' => $this->due_at?->toIso8601String(),
            'closes_at' => $this->closes_at?->toIso8601String(),
            'site_id' => $this->site_id,
            'template_id' => $this->template_id,
            'submission_id' => $this->submission_id,
            'fulfilled_at' => $this->fulfilled_at?->toIso8601String(),
        ];
    }
}
