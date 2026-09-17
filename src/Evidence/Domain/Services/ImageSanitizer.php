<?php

declare(strict_types=1);

namespace Ronda\Evidence\Domain\Services;

use GdImage;
use Ronda\Evidence\Domain\Exceptions\InvalidEvidence;

/**
 * Reescribe una imagen desde sus pixeles. RONDA-PLAN-MAESTRO.md sec. 10.4
 *
 * Volver a codificarla hace tres cosas a la vez:
 *
 * - elimina TODO el metadato (EXIF, XMP, miniaturas con la foto sin recortar):
 *   lo que importa ya se extrajo a columnas con ExifReader;
 * - descarta cualquier cosa escondida detras de los datos de imagen (un
 *   polyglot JPEG+HTML deja de serlo);
 * - aplica la orientacion EXIF, que de otro modo se perderia con el metadato y
 *   dejaria las fotos de telefono de lado.
 *
 * El SHA-256 se calcula DESPUES, sobre lo que se guarda: es el archivo que se
 * podra verificar mas adelante.
 */
final class ImageSanitizer
{
    /**
     * Limite de pixeles. Una imagen de 20 KB puede declarar 50 000 x 50 000 y
     * pedir gigas de memoria al decodificarse.
     */
    private const int MAX_PIXELS = 50_000_000;

    private const int QUALITY = 90;

    public function sanitize(string $bytes, string $mimeType, int $orientation = 1): string
    {
        $dimensiones = @getimagesizefromstring($bytes);

        if ($dimensiones === false || $dimensiones[0] < 1 || $dimensiones[1] < 1) {
            throw InvalidEvidence::unreadableImage();
        }

        if ($dimensiones[0] * $dimensiones[1] > self::MAX_PIXELS) {
            throw InvalidEvidence::unreadableImage();
        }

        $imagen = @imagecreatefromstring($bytes);

        if (! $imagen instanceof GdImage) {
            throw InvalidEvidence::unreadableImage();
        }

        $imagen = $this->orient($imagen, $orientation);

        ob_start();

        $ok = match ($mimeType) {
            'image/png' => $this->png($imagen),
            'image/webp' => imagewebp($imagen, null, self::QUALITY),
            default => imagejpeg($imagen, null, self::QUALITY),
        };

        $salida = (string) ob_get_clean();

        if (! $ok || $salida === '') {
            throw InvalidEvidence::unreadableImage();
        }

        return $salida;
    }

    private function png(GdImage $imagen): bool
    {
        // Una firma es un trazo sobre fondo transparente.
        imagealphablending($imagen, false);
        imagesavealpha($imagen, true);

        return imagepng($imagen, null, 6);
    }

    /**
     * Orientaciones EXIF 1 a 8.
     */
    private function orient(GdImage $imagen, int $orientation): GdImage
    {
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($imagen, IMG_FLIP_HORIZONTAL);
        }

        $grados = match ($orientation) {
            3, 4 => 180,
            5, 6 => 270,
            7, 8 => 90,
            default => 0,
        };

        if ($grados === 0) {
            return $imagen;
        }

        $rotada = imagerotate($imagen, $grados, 0);

        return $rotada instanceof GdImage ? $rotada : $imagen;
    }
}
