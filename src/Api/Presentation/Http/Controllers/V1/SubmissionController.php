<?php

declare(strict_types=1);

namespace Ronda\Api\Presentation\Http\Controllers\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Ronda\Api\Presentation\Http\Controllers\Concerns\HandlesApiRequests;
use Ronda\Api\Presentation\Http\Resources\SubmissionResource;
use Ronda\Evidence\Application\DecodeBase64Evidence;
use Ronda\Identity\Domain\Models\User;
use Ronda\Scheduling\Domain\Models\Obligation;
use Ronda\Submissions\Application\Actions\SubmitReport;
use Ronda\Submissions\Domain\Exceptions\CannotSubmit;
use Ronda\Submissions\Domain\Exceptions\InvalidAnswers;
use Ronda\Submissions\Domain\Models\Submission;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Reportes entregados, y la unica puerta de escritura de la v1.
 * RONDA-PLAN-MAESTRO.md sec. 13.1
 *
 * Entregar por la API es la MISMA entrega que desde el telefono: la misma
 * Action (`SubmitReport`), la misma Policy y la misma validacion contra la
 * version de la plantilla. Aqui solo se traduce lo que llega en JSON y se
 * devuelven codigos que una integracion pueda interpretar:
 *
 *   201  entregado
 *   403  esta persona no puede entregar esa obligacion
 *   409  la obligacion ya no admite entregas (cerrada o cumplida)
 *   422  las respuestas no se sostienen contra el esquema
 *
 * La diferencia entre 409 y 422 importa: con 409 no sirve reintentar; con 422,
 * tampoco, pero hay que corregir los datos. Reintentar solo tiene sentido con
 * un 5xx.
 *
 * `client_token` hace la entrega idempotente, igual que en la cola del
 * telefono: si la respuesta se pierde, repetir la llamada devuelve el MISMO
 * envio en vez de crear otro.
 */
final class SubmissionController extends Controller
{
    use HandlesApiRequests;

    /**
     * Lista los envios.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Submission::class);

        $envios = QueryBuilder::for(Submission::class)
            ->allowedFilters(...[
                AllowedFilter::exact('site_id'),
                AllowedFilter::exact('template_id'),
                AllowedFilter::exact('state'),
                AllowedFilter::callback('from', fn ($query, $valor) => $query->where('submitted_at', '>=', $valor)),
                AllowedFilter::callback('to', fn ($query, $valor) => $query->where('submitted_at', '<=', $valor)),
            ])
            ->allowedSorts(...['submitted_at'])
            ->defaultSort('-submitted_at')
            ->cursorPaginate($this->perPage());

        return SubmissionResource::collection($envios);
    }

    /**
     * Un envio.
     */
    public function show(Submission $submission): SubmissionResource
    {
        $this->authorize('view', $submission);

        return new SubmissionResource($submission);
    }

    /**
     * Entrega un reporte contra una obligacion abierta.
     */
    public function store(
        Request $request,
        SubmitReport $submit,
        DecodeBase64Evidence $decode,
    ): JsonResponse {
        $datos = $request->validate([
            'obligation_id' => ['required', 'integer'],
            'client_token' => ['nullable', 'string', 'min:8', 'max:64'],
            'answers' => ['array'],
            'evidence' => ['array'],
            'evidence.*' => ['array'],
            'evidence.*.*.name' => ['required', 'string', 'max:200'],
            'evidence.*.*.data' => ['required', 'string'],
            // Declaradas porque `validate()` devuelve SOLO lo validado: sin
            // esto, las coordenadas llegarian y se perderian aqui mismo.
            'evidence.*.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'evidence.*.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $obligacion = Obligation::query()->find($datos['obligation_id']);

        if (! $obligacion instanceof Obligation) {
            return new JsonResponse([
                'message' => __('That obligation does not exist.'),
                'code' => 'obligation_not_found',
            ], 404);
        }

        $this->authorize('submit', $obligacion);

        /** @var User $autor */
        $autor = $request->user();

        try {
            $envio = $submit(
                $obligacion,
                $autor,
                is_array($datos['answers'] ?? null) ? $datos['answers'] : [],
                evidence: $decode(
                    is_array($datos['evidence'] ?? null) ? $datos['evidence'] : [],
                    $request->ip(),
                ),
                clientToken: isset($datos['client_token']) ? (string) $datos['client_token'] : null,
            );
        } catch (InvalidAnswers $e) {
            return new JsonResponse([
                'message' => __('The answers are not valid.'),
                'code' => 'invalid_answers',
                'errors' => $e->errors,
            ], 422);
        } catch (CannotSubmit $e) {
            return new JsonResponse([
                'message' => $e->getMessage(),
                'code' => 'cannot_submit',
            ], 409);
        }

        return new JsonResponse(new SubmissionResource($envio), 201);
    }
}
