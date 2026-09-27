<?php

declare(strict_types=1);

namespace Ronda\Api\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Ronda\Forms\Domain\Models\Template;

/**
 * Una plantilla, con el esquema de su version vigente.
 * RONDA-PLAN-MAESTRO.md sec. 13.1 y ADR 0012
 *
 * El esquema se incluye porque sin el no se puede construir una entrega: quien
 * integra necesita saber que campos hay, de que tipo y cuales son
 * obligatorios.
 *
 * @mixin Template
 */
final class TemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'status' => (string) $this->status->value,
            'current_version' => $this->whenLoaded('currentVersion', fn (): ?array => $this->currentVersion === null ? null : [
                'id' => $this->currentVersion->id,
                'number' => $this->currentVersion->number,
                'fields' => $this->currentVersion->schema,
            ]),
        ];
    }
}
