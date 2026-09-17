<?php

declare(strict_types=1);

use Ronda\Evidence\Domain\EvidenceKind;
use Ronda\Evidence\Domain\Exceptions\InvalidEvidence;
use Ronda\Evidence\Domain\Services\ExifReader;
use Ronda\Evidence\Domain\Services\ImageSanitizer;
use Ronda\Evidence\Domain\ValueObjects\GeoPoint;
use Ronda\Forms\Domain\ValueObjects\FieldType;
use Tests\Support\EvidenceFixtures;

// La parte probatoria de la evidencia, sin base ni bucket. sec. 9.5
//
// Los errores caros aqui son silenciosos: una foto a 4 km que se da por buena,
// una foto de ayer que parece de hoy, o un archivo que conserva el numero de
// serie del telefono despues de «sanearse».

it('calcula la distancia entre dos puntos en metros', function (): void {
    $plazaDeArmas = GeoPoint::of(-12.0464, -77.0428);
    $miraflores = GeoPoint::of(-12.1211, -77.0297);

    // Unos 8,4 km en linea recta.
    expect($plazaDeArmas->distanceInMetersTo($miraflores))->toBeGreaterThan(8200)->toBeLessThan(8600)
        ->and($plazaDeArmas->distanceInMetersTo($plazaDeArmas))->toBe(0);
})->group('evidence');

it('rechaza coordenadas fuera de rango y tolera las ausentes', function (): void {
    expect(fn (): GeoPoint => GeoPoint::of(91, 0))->toThrow(InvalidEvidence::class)
        ->and(fn (): GeoPoint => GeoPoint::of(0, -181))->toThrow(InvalidEvidence::class)
        ->and(GeoPoint::tryFromStrings(null, '-77'))->toBeNull()
        ->and(GeoPoint::tryFromStrings('abc', '-77'))->toBeNull();
})->group('evidence');

it('lee del EXIF la fecha de captura y las coordenadas', function (): void {
    $metadata = (new ExifReader)->read(EvidenceFixtures::jpegWithExif(), 'image/jpeg');

    expect($metadata->capturedAtLocal)->toBe('2026-09-16 09:12:33')
        ->and($metadata->location)->not->toBeNull()
        ->and(round((float) $metadata->location?->latitude, 4))->toBe(-12.0464)
        ->and(round((float) $metadata->location?->longitude, 4))->toBe(-77.0428)
        // Sin desfase en el EXIF, la hora es la del reloj de la sede.
        ->and($metadata->capturedAt('America/Lima')?->toDateTimeString())->toBe('2026-09-16 14:12:33');
})->group('evidence');

it('deja sin datos una foto sin EXIF o con fecha a ceros, sin fallar', function (): void {
    $reader = new ExifReader;

    expect($reader->read(EvidenceFixtures::jpeg(), 'image/jpeg')->location)->toBeNull()
        ->and($reader->read(EvidenceFixtures::jpegWithExif(dateTimeOriginal: '0000:00:00 00:00:00'), 'image/jpeg')->capturedAtLocal)->toBeNull()
        ->and($reader->read(EvidenceFixtures::png(), 'image/png')->capturedAtLocal)->toBeNull();
})->group('evidence');

it('al sanear quita todo el EXIF', function (): void {
    $original = EvidenceFixtures::jpegWithExif();
    $saneada = (new ImageSanitizer)->sanitize($original, 'image/jpeg');

    // La prueba se sostiene solo si el original SI tenia EXIF legible.
    expect((new ExifReader)->read($original, 'image/jpeg')->location)->not->toBeNull()
        ->and((new ExifReader)->read($saneada, 'image/jpeg')->location)->toBeNull()
        ->and((new ExifReader)->read($saneada, 'image/jpeg')->capturedAtLocal)->toBeNull()
        ->and(getimagesizefromstring($saneada))->not->toBeFalse();
})->group('evidence');

it('aplica la orientacion del telefono antes de perder el EXIF', function (): void {
    // Orientacion 6: la camara guardo 40x20 pero la foto se ve 20x40.
    $saneada = (new ImageSanitizer)->sanitize(EvidenceFixtures::jpeg(40, 20), 'image/jpeg', 6);
    $dimensiones = getimagesizefromstring($saneada);

    expect([$dimensiones[0] ?? 0, $dimensiones[1] ?? 0])->toBe([20, 40]);
})->group('evidence');

it('no acepta como imagen lo que no lo es', function (): void {
    expect(fn (): string => (new ImageSanitizer)->sanitize(EvidenceFixtures::htmlDisguisedAsPhoto(), 'image/jpeg'))
        ->toThrow(InvalidEvidence::class);
})->group('evidence');

it('no admite SVG en ninguna clase de evidencia', function (): void {
    foreach (EvidenceKind::cases() as $kind) {
        expect($kind->allowedMimeTypes())->not->toContain('image/svg+xml');
    }

    expect(EvidenceKind::Signature->allowedMimeTypes())->toBe(['image/png'])
        ->and(EvidenceKind::forFieldType(FieldType::Photo))->toBe(EvidenceKind::Photo)
        ->and(EvidenceKind::forFieldType(FieldType::Text))->toBeNull();
})->group('evidence');
