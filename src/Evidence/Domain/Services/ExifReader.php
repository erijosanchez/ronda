<?php

declare(strict_types=1);

namespace Ronda\Evidence\Domain\Services;

use Ronda\Evidence\Domain\Exceptions\InvalidEvidence;
use Ronda\Evidence\Domain\ValueObjects\GeoPoint;
use Ronda\Evidence\Domain\ValueObjects\ImageMetadata;
use Throwable;

/**
 * Extrae fecha de captura, coordenadas y orientacion del EXIF de una foto.
 * RONDA-PLAN-MAESTRO.md sec. 9.5
 *
 * Tolerante: un EXIF ausente o roto no invalida la foto, solo la deja sin esos
 * datos. Muchos navegadores moviles quitan la ubicacion al subir; por eso la
 * ubicacion del dispositivo es la segunda fuente (ver StoreEvidence).
 */
final class ExifReader
{
    public function read(string $bytes, string $mimeType): ImageMetadata
    {
        // Solo JPEG lleva EXIF de camara en la practica. PNG y WEBP pueden
        // llevarlo, pero exif_read_data no los lee.
        if ($mimeType !== 'image/jpeg') {
            return ImageMetadata::empty();
        }

        $datos = $this->exif($bytes);

        if ($datos === null) {
            return ImageMetadata::empty();
        }

        return new ImageMetadata(
            capturedAtLocal: $this->capturedAt($datos),
            utcOffset: $this->offset($datos),
            location: $this->location($datos),
            orientation: $this->orientation($datos),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function exif(string $bytes): ?array
    {
        $stream = fopen('php://memory', 'r+b');

        if ($stream === false) {
            return null;
        }

        try {
            fwrite($stream, $bytes);
            rewind($stream);

            // El @ es deliberado: exif_read_data emite avisos ante cualquier
            // etiqueta rara, y un aviso no debe tumbar una entrega.
            $datos = @exif_read_data($stream);

            return is_array($datos) ? $datos : null;
        } catch (Throwable) {
            return null;
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function capturedAt(array $datos): ?string
    {
        $valor = $datos['DateTimeOriginal'] ?? $datos['DateTimeDigitized'] ?? null;

        if (! is_string($valor) || preg_match('/^(\d{4}):(\d{2}):(\d{2}) (\d{2}:\d{2}:\d{2})$/', trim($valor), $m) !== 1) {
            return null;
        }

        // Camaras sin hora puesta escriben ceros.
        if ($m[1] === '0000') {
            return null;
        }

        return "{$m[1]}-{$m[2]}-{$m[3]} {$m[4]}";
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function offset(array $datos): ?string
    {
        $valor = $datos['OffsetTimeOriginal'] ?? $datos['OffsetTime'] ?? null;

        return is_string($valor) && preg_match('/^[+-]\d{2}:\d{2}$/', trim($valor)) === 1 ? trim($valor) : null;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function location(array $datos): ?GeoPoint
    {
        $lat = $this->degrees($datos['GPSLatitude'] ?? null);
        $lng = $this->degrees($datos['GPSLongitude'] ?? null);

        if ($lat === null || $lng === null) {
            return null;
        }

        if (($datos['GPSLatitudeRef'] ?? 'N') === 'S') {
            $lat = -$lat;
        }

        if (($datos['GPSLongitudeRef'] ?? 'E') === 'W') {
            $lng = -$lng;
        }

        try {
            return GeoPoint::of($lat, $lng);
        } catch (InvalidEvidence) {
            return null;
        }
    }

    /**
     * Grados, minutos y segundos como racionales EXIF (`"12/1"`) a grados
     * decimales.
     */
    private function degrees(mixed $valor): ?float
    {
        if (! is_array($valor) || count($valor) !== 3) {
            return null;
        }

        $partes = [];

        foreach (array_values($valor) as $racional) {
            $numero = $this->rational($racional);

            if ($numero === null) {
                return null;
            }

            $partes[] = $numero;
        }

        return $partes[0] + $partes[1] / 60 + $partes[2] / 3600;
    }

    private function rational(mixed $valor): ?float
    {
        if (! is_string($valor) || preg_match('/^(\d+)\/(\d+)$/', $valor, $m) !== 1 || (int) $m[2] === 0) {
            return null;
        }

        return (int) $m[1] / (int) $m[2];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function orientation(array $datos): int
    {
        $valor = $datos['Orientation'] ?? 1;

        return is_int($valor) && $valor >= 1 && $valor <= 8 ? $valor : 1;
    }
}
