<?php

declare(strict_types=1);

namespace Ronda\Evidence\Application\Data;

/**
 * Un archivo tal como llega, todavia sin validar. RONDA-PLAN-MAESTRO.md
 * sec. 9.5
 *
 * Lleva el CONTENIDO y no una ruta temporal: quien valida tiene que mirar los
 * bytes, y asi la Action no depende de donde dejo el archivo Livewire. Una
 * evidencia pesa como mucho unos megas (config security.evidence).
 *
 * El nombre original es solo informativo: nunca decide el tipo ni la ruta.
 */
final readonly class EvidenceUpload
{
    public function __construct(
        public string $contents,
        public string $originalName,
        public ?string $deviceLatitude = null,
        public ?string $deviceLongitude = null,
        public ?string $ipAddress = null,
    ) {}
}
