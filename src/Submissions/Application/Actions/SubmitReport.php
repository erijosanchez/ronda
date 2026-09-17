<?php

declare(strict_types=1);

namespace Ronda\Submissions\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Ronda\Evidence\Application\Actions\DiscardEvidence;
use Ronda\Evidence\Application\Data\EvidenceUpload;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Forms\Domain\Models\TemplateVersion;
use Ronda\Identity\Domain\Models\User;
use Ronda\Scheduling\Domain\Models\Obligation;
use Ronda\Scheduling\Domain\States\Fulfilled;
use Ronda\Scheduling\Domain\States\Pending;
use Ronda\Submissions\Domain\Events\SubmissionSubmitted;
use Ronda\Submissions\Domain\Exceptions\CannotSubmit;
use Ronda\Submissions\Domain\Exceptions\InvalidAnswers;
use Ronda\Submissions\Domain\Models\Submission;
use Ronda\Submissions\Domain\Services\AnswerValidator;
use Ronda\Submissions\Domain\Services\ReportableValues;
use Ronda\Submissions\Domain\States\Submitted;
use Ronda\Workflow\Application\Actions\RecordTransition;
use Throwable;

/**
 * Entrega un reporte contra una obligacion. RONDA-PLAN-MAESTRO.md sec. 9.1,
 * ADR 0008 y ADR 0012.
 *
 * Es donde las cuatro primeras piezas del motor se tocan: la plantilla dice que
 * se pide, la obligacion dice cuando, y el envio es lo que llego.
 *
 * En UNA transaccion (regla 3), porque escribe tres tablas que tienen que
 * cuadrar entre si:
 *
 *   1. `submissions` con la respuesta completa (la fuente de verdad);
 *   2. `submission_values` con la copia tipada de lo reportable (ADR 0012:
 *      replicar dentro de la misma transaccion es lo que evita que diverjan);
 *   3. `obligations`, que pasa a `fulfilled`.
 *
 * Un envio sin obligacion cumplida deja el KPI con un incumplimiento que no lo
 * era; una obligacion cumplida sin envio, con un cumplimiento sin nada detras.
 *
 * La evidencia (fotos, archivos, firma) se guarda DENTRO de la misma
 * transaccion: cada archivo es una fila de `attachments` que se deshace con el
 * resto. El objeto en el bucket no se deshace solo; si algo falla despues de
 * subirlo, se borra con DiscardEvidence antes de propagar el error.
 *
 * La autorizacion NO vive aqui: la decide ObligationPolicy antes de invocar.
 */
final readonly class SubmitReport
{
    public function __construct(
        private ConnectionInterface $connection,
        private AnswerValidator $validator,
        private ReportableValues $reportable,
        private StoreAnswerEvidence $storeAnswerEvidence,
        private DiscardEvidence $discardEvidence,
        private RecordTransition $recordTransition,
        private Dispatcher $events,
    ) {}

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, list<EvidenceUpload>>  $evidence  archivos por clave de campo
     *
     * @throws CannotSubmit
     * @throws InvalidAnswers
     */
    public function __invoke(
        Obligation $obligation,
        User $author,
        array $answers,
        ?CarbonImmutable $now = null,
        array $evidence = [],
    ): Submission {
        $now ??= CarbonImmutable::now('UTC');

        /** @var list<string> $escritas rutas ya subidas al bucket */
        $escritas = [];

        try {
            // Closure con `&` y no arrow function: una arrow function captura por
            // valor, y la limpieza de abajo veria la lista vacia.
            return $this->connection->transaction(
                function () use ($obligation, $author, $answers, $now, $evidence, &$escritas): Submission {
                    return $this->submit($obligation, $author, $answers, $now, $evidence, $escritas);
                },
            );
        } catch (Throwable $e) {
            ($this->discardEvidence)($escritas);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, list<EvidenceUpload>>  $evidence
     * @param  list<string>  $escritas
     */
    private function submit(
        Obligation $obligation,
        User $author,
        array $answers,
        CarbonImmutable $now,
        array $evidence,
        array &$escritas,
    ): Submission {
        // Se relee con bloqueo. Dos pestanas enviando a la vez leerian las
        // dos `pending` sin el; con el, la segunda espera y ve `fulfilled`.
        // La restriccion unica de `submissions.obligation_id` es la ultima
        // barrera si algo se salta esto.
        /** @var Obligation $obligation */
        $obligation = Obligation::query()->lockForUpdate()->findOrFail($obligation->getKey());

        $this->guardWindow($obligation, $now);

        $version = $this->currentVersion($obligation->template_id);
        $schema = $version->formSchema();

        // Primero se valida: si las respuestas no se sostienen, no se
        // escribe nada y la transaccion se deshace entera.
        $limpias = $this->validator->validate(
            $schema,
            $answers,
            array_map(count(...), $evidence),
        );

        $tarde = $now->greaterThan($obligation->due_at);

        $submission = Submission::create([
            'template_id' => $obligation->template_id,
            'template_version_id' => $version->getKey(),
            'site_id' => $obligation->site_id,
            'obligation_id' => $obligation->getKey(),
            'author_id' => $author->getKey(),
            'state' => Submitted::$name,
            'data' => $limpias,
            'submitted_at' => $now,
            'is_late' => $tarde,
            'minutes_late' => $tarde ? (int) floor($obligation->due_at->diffInMinutes($now, true)) : 0,
        ]);

        $limpias = ($this->storeAnswerEvidence)($submission, $schema, $limpias, $evidence, $author, $escritas);

        if ($limpias !== $submission->data) {
            $submission->forceFill(['data' => $limpias])->save();
        }

        $filas = $this->reportable->extract($schema, $limpias);

        if ($filas !== []) {
            $submission->values()->createMany($filas);
        }

        $obligation->status->transitionTo(Fulfilled::class);
        $obligation->forceFill([
            'submission_id' => $submission->getKey(),
            'fulfilled_at' => $now,
        ])->save();

        // Primera linea del historial del envio (Workflow).
        ($this->recordTransition)($submission, null, Submitted::$name, $author);

        // De aqui cuelga lo que pasa despues: avisar a quien revisa (sec. 6.2).
        $this->events->dispatch(new SubmissionSubmitted($submission->id, (int) $author->getKey()));

        return $submission;
    }

    private function guardWindow(Obligation $obligation, CarbonImmutable $now): void
    {
        if (! $obligation->status instanceof Pending) {
            throw CannotSubmit::notPending($obligation->status->getValue());
        }

        if ($now->lessThan($obligation->opens_at)) {
            throw CannotSubmit::notOpenYet($obligation->opens_at->toDateTimeString());
        }

        // Se comprueba el cierre por la hora y no solo por el estado: el job que
        // marca `missed` corre cada hora, y entre el cierre y la siguiente pasada
        // la obligacion sigue `pending` aunque ya no admita entregas.
        if ($now->greaterThan($obligation->closes_at)) {
            throw CannotSubmit::closed();
        }
    }

    /**
     * La version vigente AHORA, no la que habia al materializar. Es con la que
     * la sede ve el formulario, y queda registrada en el envio.
     */
    private function currentVersion(int $templateId): TemplateVersion
    {
        $template = Template::query()->with('currentVersion')->find($templateId);
        $version = $template?->currentVersion;

        if (! $version instanceof TemplateVersion) {
            throw CannotSubmit::templateWithoutVersion();
        }

        return $version;
    }
}
