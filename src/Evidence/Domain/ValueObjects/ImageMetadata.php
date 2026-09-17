<?php

declare(strict_types=1);

namespace Ronda\Evidence\Domain\ValueObjects;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Lo que interesa del EXIF de una foto, y nada mas. RONDA-PLAN-MAESTRO.md
 * sec. 9.5 y 10.4
 *
 * La fecha de captura y las coordenadas son la evidencia: se guardan en
 * columnas y el resto del EXIF (modelo del telefono, numero de serie,
 * miniaturas) se descarta con el archivo saneado.
 */
final readonly class ImageMetadata
{
    /**
     * @param  string|null  $capturedAtLocal  `Y-m-d H:i:s` en el reloj de la camara
     * @param  string|null  $utcOffset  `+HH:MM` si la camara lo registro
     * @param  int  $orientation  etiqueta EXIF Orientation (1 = derecha)
     */
    public function __construct(
        public ?string $capturedAtLocal = null,
        public ?string $utcOffset = null,
        public ?GeoPoint $location = null,
        public int $orientation = 1,
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    /**
     * El instante de captura en UTC.
     *
     * EXIF guarda la hora del reloj de la camara SIN zona. Si la camara anoto
     * el desfase se usa; si no, se asume la zona de la sede, que es donde se
     * supone que se tomo. Lo que se busca detectar es una foto de ayer, y para
     * eso un error de zona no alcanza.
     */
    public function capturedAt(string $fallbackTimezone): ?CarbonImmutable
    {
        if ($this->capturedAtLocal === null) {
            return null;
        }

        try {
            $zona = $this->utcOffset ?? $fallbackTimezone;

            return CarbonImmutable::createFromFormat('Y-m-d H:i:s', $this->capturedAtLocal, $zona)?->utc();
        } catch (Throwable) {
            return null;
        }
    }
}
