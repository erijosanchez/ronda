<?php

declare(strict_types=1);

namespace Ronda\Submissions\Application\Actions;

use Ronda\Evidence\Application\Actions\StoreEvidence;
use Ronda\Evidence\Application\Data\EvidenceUpload;
use Ronda\Evidence\Domain\EvidenceKind;
use Ronda\Evidence\Domain\Exceptions\InvalidEvidence;
use Ronda\Forms\Domain\ValueObjects\Field;
use Ronda\Forms\Domain\ValueObjects\FormSchema;
use Ronda\Identity\Domain\Models\User;
use Ronda\Submissions\Domain\Exceptions\InvalidAnswers;
use Ronda\Submissions\Domain\Models\Submission;

/**
 * Guarda la evidencia de las respuestas ya validadas y pone en ellas los ids de
 * sus archivos. RONDA-PLAN-MAESTRO.md sec. 9.5
 *
 * La usan la entrega (SubmitReport) y la correccion (CorrectSubmission): que
 * las dos pasen por el mismo sitio es lo que impide que una correccion cuele un
 * archivo que la entrega habria rechazado.
 *
 * - Un campo con archivos nuevos queda con los ids de esos archivos.
 * - Un campo que el validador acepto sin archivos nuevos conserva los de
 *   `$previous` (en una correccion, lo que ya estaba y no se reemplazo).
 * - Lo que llego para un campo inexistente, oculto o de otro tipo no se guarda.
 *
 * No abre transaccion: la abre quien la invoca. Cada ruta escrita en el bucket
 * se anade a `$written` para que el llamador pueda deshacerla si la transaccion
 * falla (DiscardEvidence).
 */
final readonly class StoreAnswerEvidence
{
    public function __construct(
        private StoreEvidence $storeEvidence,
    ) {}

    /**
     * @param  array<string, string|bool|list<string>>  $answers  validadas por AnswerValidator
     * @param  array<string, list<EvidenceUpload>>  $evidence  archivos nuevos por campo
     * @param  array<string, list<string>>  $previous  ids ya guardados por campo
     * @param  list<string>  $written
     * @return array<string, string|bool|list<string>>
     *
     * @throws InvalidAnswers
     */
    public function __invoke(
        Submission $submission,
        FormSchema $schema,
        array $answers,
        array $evidence,
        User $author,
        array &$written,
        array $previous = [],
    ): array {
        foreach ($schema->fields as $field) {
            $clase = EvidenceKind::forFieldType($field->type);

            if (! $clase instanceof EvidenceKind || ! array_key_exists($field->key, $answers)) {
                continue;
            }

            $nuevos = $evidence[$field->key] ?? [];

            $answers[$field->key] = $nuevos === []
                ? $previous[$field->key] ?? []
                : $this->store($submission, $field, $clase, $nuevos, $author, $written);
        }

        return $answers;
    }

    /**
     * @param  list<EvidenceUpload>  $archivos
     * @param  list<string>  $written
     * @return list<string>
     */
    private function store(
        Submission $submission,
        Field $field,
        EvidenceKind $clase,
        array $archivos,
        User $author,
        array &$written,
    ): array {
        $ids = [];

        foreach ($archivos as $archivo) {
            try {
                $adjunto = ($this->storeEvidence)($submission, $field->key, $clase, $archivo, $author);
            } catch (InvalidEvidence $e) {
                // Con la clave del campo, para que la pantalla lo muestre donde
                // corresponde.
                throw new InvalidAnswers([$field->key => "«{$field->label}»: {$e->getMessage()}"]);
            }

            $written[] = $adjunto->path;
            $ids[] = (string) $adjunto->getKey();
        }

        return $ids;
    }
}
