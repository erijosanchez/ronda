<?php

declare(strict_types=1);

namespace Ronda\Evidence\Domain\ValueObjects;

use Ronda\Evidence\Domain\Exceptions\InvalidEvidence;

/**
 * Un punto sobre la Tierra. RONDA-PLAN-MAESTRO.md sec. 9.5
 *
 * Sirve para una sola cosa: saber a cuantos metros de la sede se tomo una foto.
 * «Si la foto se tomo a 4 km del local, la revision lo senala.»
 */
final readonly class GeoPoint
{
    /** Radio medio de la Tierra, en metros. */
    private const float EARTH_RADIUS_METERS = 6_371_008.8;

    private function __construct(
        public float $latitude,
        public float $longitude,
    ) {}

    public static function of(float $latitude, float $longitude): self
    {
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180
            || is_nan($latitude) || is_nan($longitude)) {
            throw InvalidEvidence::invalidCoordinates();
        }

        return new self($latitude, $longitude);
    }

    /**
     * Desde texto decimal (como llega de un formulario o de una columna
     * `decimal`). Null si falta alguna de las dos o no es un numero.
     */
    public static function tryFromStrings(?string $latitude, ?string $longitude): ?self
    {
        if ($latitude === null || $longitude === null
            || ! is_numeric(trim($latitude)) || ! is_numeric(trim($longitude))) {
            return null;
        }

        return self::of((float) $latitude, (float) $longitude);
    }

    /**
     * Distancia sobre la superficie (haversine), en metros enteros.
     *
     * Haversine asume una esfera: a la escala de «esta foto se tomo en el local
     * o no» el error es de metros, muy por debajo del de un GPS de telefono.
     */
    public function distanceInMetersTo(self $other): int
    {
        $lat1 = deg2rad($this->latitude);
        $lat2 = deg2rad($other->latitude);
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad($other->longitude - $this->longitude);

        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return (int) round(2 * self::EARTH_RADIUS_METERS * asin(min(1.0, sqrt($a))));
    }

    /**
     * Siete decimales: alrededor de un centimetro, lo que guarda la columna.
     */
    public function latitudeAsString(): string
    {
        return number_format($this->latitude, 7, '.', '');
    }

    public function longitudeAsString(): string
    {
        return number_format($this->longitude, 7, '.', '');
    }
}
