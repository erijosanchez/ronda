<?php

declare(strict_types=1);

namespace Ronda\Submissions\Presentation\Http;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ronda\Evidence\Application\Data\EvidenceUpload;
use Ronda\Identity\Domain\Models\User;
use Ronda\Scheduling\Domain\Models\Obligation;
use Ronda\Submissions\Application\Actions\SubmitReport;
use Ronda\Submissions\Domain\Exceptions\CannotSubmit;
use Ronda\Submissions\Domain\Exceptions\InvalidAnswers;

/**
 * Recibe una entrega que estuvo esperando en el telefono.
 * RONDA-PLAN-MAESTRO.md sec. 13.3
 *
 * La cola de envio del dispositivo manda aqui lo que se lleno sin senal: las
 * respuestas y las fotos en base64, con la huella (`client_token`) que generó
 * el telefono antes de guardarlo.
 *
 * Es la misma entrega de siempre: valida, invoca SubmitReport y devuelve
 * (regla 1). Lo unico propio de esta puerta es que responde en JSON con codigos
 * que la cola sabe interpretar:
 *
 *   200  entregado (o reconocido un reintento: mismo envio, no otro)
 *   403  esta persona no puede entregar esa obligacion
 *   409  la obligacion ya no admite entregas (cerrada o cumplida)
 *   422  las respuestas no se sostienen
 *
 * La diferencia entre 409 y 422 importa: con 409 la cola deja de reintentar y
 * lo dice; con 422 tambien para, porque reintentar lo mismo dara lo mismo. Solo
 * un fallo de red se reintenta.
 */
final class OutboxSubmitController extends Controller
{
    public function __invoke(Request $request, Obligation $obligation, SubmitReport $submit, Gate $gate): JsonResponse
    {
        $gate->authorize('submit', $obligation);

        $datos = $request->validate([
            'client_token' => ['required', 'string', 'min:8', 'max:64'],
            'answers' => ['array'],
            'evidence' => ['array'],
            'evidence.*' => ['array'],
            'evidence.*.*.name' => ['required', 'string', 'max:200'],
            // El contenido llega en base64: pesa un tercio mas que el archivo.
            'evidence.*.*.data' => ['required', 'string', 'max:'.$this->maxBase64Length()],
            // La ubicacion del momento en que se lleno el reporte. Van
            // declaradas porque `validate()` devuelve SOLO lo validado: sin
            // esto llegarian y se perderian aqui mismo.
            'evidence.*.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'evidence.*.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        /** @var User $author */
        $author = $request->user();

        try {
            $submission = $submit(
                $obligation,
                $author,
                is_array($datos['answers'] ?? null) ? $datos['answers'] : [],
                evidence: $this->evidence($request, is_array($datos['evidence'] ?? null) ? $datos['evidence'] : []),
                clientToken: (string) $datos['client_token'],
            );
        } catch (InvalidAnswers $e) {
            return response()->json(['code' => 'invalid_answers', 'errors' => $e->errors], 422);
        } catch (CannotSubmit $e) {
            return response()->json(['code' => 'cannot_submit', 'message' => $e->getMessage()], 409);
        }

        return response()->json([
            'code' => 'submitted',
            'submission_id' => $submission->id,
            'url' => route('submissions.show', $submission),
        ]);
    }

    /**
     * Convierte lo que llego en base64 en archivos, sin escribirlos en disco:
     * StoreEvidence trabaja con el contenido y es quien decide si valen.
     *
     * @param  array<string, array<int, array{name?: string, data?: string}>>  $evidence
     * @return array<string, list<EvidenceUpload>>
     */
    private function evidence(Request $request, array $evidence): array
    {
        $porCampo = [];

        foreach ($evidence as $campo => $archivos) {
            foreach ($archivos as $archivo) {
                $contenido = base64_decode((string) ($archivo['data'] ?? ''), true);

                if ($contenido === false || $contenido === '') {
                    continue;
                }

                $porCampo[(string) $campo][] = new EvidenceUpload(
                    contents: $contenido,
                    originalName: (string) ($archivo['name'] ?? 'archivo'),
                    // La ubicacion la tomo el telefono cuando se lleno el
                    // reporte, no ahora: puede haberse movido desde entonces.
                    deviceLatitude: $this->texto($archivo['latitude'] ?? null),
                    deviceLongitude: $this->texto($archivo['longitude'] ?? null),
                    ipAddress: $request->ip(),
                );
            }
        }

        return $porCampo;
    }

    private function texto(mixed $valor): ?string
    {
        return is_scalar($valor) ? (string) $valor : null;
    }

    /**
     * Tope del texto base64 de un archivo: el maximo de evidencia mas el tercio
     * que anade la codificacion. Sin esto, un cuerpo enorme llegaria hasta
     * StoreEvidence solo para que lo rechace.
     */
    private function maxBase64Length(): int
    {
        $kilobytes = (int) config('security.evidence.max_kilobytes', 10240);

        return (int) ceil($kilobytes * 1024 * 1.4);
    }
}
