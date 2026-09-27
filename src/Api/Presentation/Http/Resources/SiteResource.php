<?php

declare(strict_types=1);

namespace Ronda\Api\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Ronda\Directory\Domain\Models\Site;

/**
 * Una sede, tal como la ve la API. RONDA-PLAN-MAESTRO.md sec. 13.1
 *
 * El recurso existe para que el modelo NO sea el contrato: añadir una columna
 * a `sites` no puede cambiar lo que reciben las integraciones de los clientes.
 * Lo que sale de aqui es lo que se prometio, y crece solo a proposito.
 *
 * @mixin Site
 */
final class SiteResource extends JsonResource
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
            'timezone' => $this->timezone,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'zone' => $this->whenLoaded('zone', fn (): ?array => $this->zone === null ? null : [
                'id' => $this->zone->id,
                'name' => $this->zone->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
