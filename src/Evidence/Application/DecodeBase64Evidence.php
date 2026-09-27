<?php

declare(strict_types=1);

namespace Ronda\Evidence\Application;

use Ronda\Evidence\Application\Data\EvidenceUpload;

/**
 * Convierte evidencia que llego en base64 en archivos para StoreEvidence.
 * RONDA-PLAN-MAESTRO.md sec. 13.1 y 13.3
 *
 * Existe porque entran por dos puertas —la cola de envio del telefono y la API
 * publica— y las dos reciben lo mismo. Estaba en la puerta de la cola; cuando
 * llego la API, copiarlo habria dejado dos sitios donde arreglar el mismo
 * fallo de decodificacion.
 *
 * No escribe nada en disco: StoreEvidence trabaja con el contenido y es quien
 * decide si vale (tipo por contenido, tamano, saneado y huella).
 */
final readonly class DecodeBase64Evidence
{
    /**
     * @param  array<string, array<int, array{name?: string, data?: string, latitude?: mixed, longitude?: mixed}>>  $evidence
     * @return array<string, list<EvidenceUpload>>
     */
    public function __invoke(array $evidence, ?string $ipAddress = null): array
    {
        $porCampo = [];

        foreach ($evidence as $campo => $archivos) {
            foreach ($archivos as $archivo) {
                // `strict: true` a proposito: una cadena que no es base64
                // valido se descarta en vez de convertirse en basura binaria
                // que despues StoreEvidence rechazaria por tipo.
                $contenido = base64_decode((string) ($archivo['data'] ?? ''), true);

                if ($contenido === false || $contenido === '') {
                    continue;
                }

                $porCampo[(string) $campo][] = new EvidenceUpload(
                    contents: $contenido,
                    originalName: (string) ($archivo['name'] ?? 'archivo'),
                    // La ubicacion es la del momento en que se lleno el
                    // reporte, no la de ahora: puede haberse movido desde
                    // entonces, y lo que vale es donde se tomo la foto.
                    deviceLatitude: $this->texto($archivo['latitude'] ?? null),
                    deviceLongitude: $this->texto($archivo['longitude'] ?? null),
                    ipAddress: $ipAddress,
                );
            }
        }

        return $porCampo;
    }

    private function texto(mixed $valor): ?string
    {
        return is_scalar($valor) ? (string) $valor : null;
    }
}
