<?php

declare(strict_types=1);

namespace Ronda\Directory\Application\Data;

/**
 * Datos de una sede, ya validados. RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * Clase `readonly` propia y no `Spatie\LaravelData\Data`: PHP no permite que
 * una clase readonly extienda una que no lo es, y el test de arquitectura
 * exige DTOs readonly. Ver docs/HANDOFF.md.
 *
 * Las horas viajan como texto `HH:MM` y las fechas como `Y-m-d` porque es lo
 * que entrega el formulario. Convertirlas a objetos aqui no aportaria nada:
 * van directas a columnas `time` y `date`.
 */
final readonly class SiteData
{
    public function __construct(
        public string $code,
        public string $name,
        public string $timezone,
        public ?int $zoneId = null,
        public ?string $address = null,
        public ?string $latitude = null,
        public ?string $longitude = null,
        public ?string $opensAt = null,
        public ?string $closesAt = null,
        public ?string $activeFrom = null,
        public ?string $activeUntil = null,
    ) {}

    /**
     * Atributos tal como los espera el modelo.
     *
     * Vive aqui y no en cada Action para que crear y editar no puedan
     * desincronizarse al anadir una columna.
     *
     * @return array<string, string|int|null>
     */
    public function toAttributes(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'zone_id' => $this->zoneId,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'timezone' => $this->timezone,
            'opens_at' => $this->opensAt,
            'closes_at' => $this->closesAt,
            'active_from' => $this->activeFrom,
            'active_until' => $this->activeUntil,
        ];
    }
}
