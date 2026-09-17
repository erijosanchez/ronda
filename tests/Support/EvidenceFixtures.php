<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Archivos de prueba para la evidencia, construidos en memoria.
 *
 * Sin binarios en el repositorio: una foto con EXIF se arma byte a byte, y asi
 * cada prueba dice exactamente que fecha y que coordenadas lleva.
 */
final class EvidenceFixtures
{
    /**
     * Un JPEG real de `$width` x `$height`, sin metadatos.
     */
    public static function jpeg(int $width = 40, int $height = 20): string
    {
        $imagen = imagecreatetruecolor($width, $height);
        imagefilledrectangle($imagen, 0, 0, $width - 1, $height - 1, (int) imagecolorallocate($imagen, 200, 30, 30));

        ob_start();
        imagejpeg($imagen, null, 90);

        return (string) ob_get_clean();
    }

    public static function png(int $width = 40, int $height = 20): string
    {
        $imagen = imagecreatetruecolor($width, $height);

        ob_start();
        imagepng($imagen);

        return (string) ob_get_clean();
    }

    /**
     * Un JPEG con un bloque EXIF: fecha de captura, coordenadas y orientacion.
     *
     * Estructura TIFF little-endian con tres IFD: IFD0 (orientacion y punteros),
     * Exif (DateTimeOriginal) y GPS. Los offsets son relativos al inicio del
     * TIFF.
     *
     * @param  string  $dateTimeOriginal  `YYYY:MM:DD HH:MM:SS`
     * @param  array{0: int, 1: int, 2: int}  $latDms  grados, minutos y centesimas de segundo
     * @param  array{0: int, 1: int, 2: int}  $lngDms
     */
    public static function jpegWithExif(
        string $dateTimeOriginal = '2026:09:16 09:12:33',
        array $latDms = [12, 2, 4704],
        string $latRef = 'S',
        array $lngDms = [77, 2, 3408],
        string $lngRef = 'W',
        int $orientation = 1,
        int $width = 40,
        int $height = 20,
    ): string {
        $entrada = static fn (int $tag, int $type, int $count, string $value): string => pack('vvV', $tag, $type, $count).str_pad($value, 4, "\0");

        $ifd0 = 8;
        $exifIfd = $ifd0 + 2 + 3 * 12 + 4;          // 50
        $fecha = $exifIfd + 2 + 12 + 4;              // 68
        $gpsIfd = $fecha + 20;                       // 88
        $latData = $gpsIfd + 2 + 4 * 12 + 4;         // 142
        $lngData = $latData + 24;                    // 166

        $tiff = 'II'.pack('vV', 42, $ifd0);

        $tiff .= pack('v', 3)
            .$entrada(0x0112, 3, 1, pack('v', $orientation))
            .$entrada(0x8769, 4, 1, pack('V', $exifIfd))
            .$entrada(0x8825, 4, 1, pack('V', $gpsIfd))
            .pack('V', 0);

        $tiff .= pack('v', 1)
            .$entrada(0x9003, 2, 20, pack('V', $fecha))
            .pack('V', 0);

        $tiff .= str_pad($dateTimeOriginal, 19)."\0";

        $tiff .= pack('v', 4)
            .$entrada(0x0001, 2, 2, $latRef."\0")
            .$entrada(0x0002, 5, 3, pack('V', $latData))
            .$entrada(0x0003, 2, 2, $lngRef."\0")
            .$entrada(0x0004, 5, 3, pack('V', $lngData))
            .pack('V', 0);

        $racionales = static fn (array $dms): string => pack('VV', $dms[0], 1).pack('VV', $dms[1], 1).pack('VV', $dms[2], 100);

        $tiff .= $racionales($latDms).$racionales($lngDms);

        $app1 = "Exif\0\0".$tiff;
        $segmento = "\xFF\xE1".pack('n', strlen($app1) + 2).$app1;

        $jpeg = self::jpeg($width, $height);

        // Justo despues de SOI (FF D8).
        return substr($jpeg, 0, 2).$segmento.substr($jpeg, 2);
    }

    /**
     * Un HTML con extension y nombre de foto. Lo que tiene que rechazarse.
     */
    public static function htmlDisguisedAsPhoto(): string
    {
        return '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>';
    }

    public static function pngDataUrl(): string
    {
        return 'data:image/png;base64,'.base64_encode(self::png(120, 40));
    }
}
