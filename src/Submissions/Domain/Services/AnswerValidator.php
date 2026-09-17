<?php

declare(strict_types=1);

namespace Ronda\Submissions\Domain\Services;

use Carbon\CarbonImmutable;
use Ronda\Evidence\Domain\EvidenceKind;
use Ronda\Forms\Domain\ValueObjects\Field;
use Ronda\Forms\Domain\ValueObjects\FieldType;
use Ronda\Forms\Domain\ValueObjects\FormSchema;
use Ronda\Forms\Domain\ValueObjects\VisibilityCondition;
use Ronda\Submissions\Domain\Exceptions\InvalidAnswers;
use Throwable;

/**
 * Valida y normaliza las respuestas contra una version del formulario.
 * RONDA-PLAN-MAESTRO.md sec. 9.2
 *
 * Devuelve SOLO lo que se debe guardar: campos visibles, respondibles y con
 * valor, ya normalizados. Lo que llega de mas (un campo que no existe, o uno
 * oculto por su condicion) se descarta en vez de guardarse: `data` es la fuente
 * de verdad y no puede acumular basura del navegador.
 *
 * Los numeros y los importes se guardan como TEXTO decimal, no como float. Un
 * arqueo de 1234.10 convertido a float y vuelto a leer no siempre es 1234.10.
 *
 * Foto, archivo y firma no viajan en `answers`: son archivos, y su contenido lo
 * valida Evidence. Aqui solo se decide si el campo los exige y cuantos admite, a
 * partir de cuantos llegaron (`$evidenceCounts`). Un campo con evidencia
 * aceptada sale con una lista vacia como marcador; SubmitReport la sustituye
 * por los ids de los archivos una vez guardados.
 *
 * La tabla de filas todavia no se puede responder: necesita un editor propio.
 * Si es obligatoria y visible, el envio se rechaza con un mensaje que lo dice.
 * Rechazar es preferible a aceptar un arqueo al que le falta lo que el cliente
 * exigio.
 */
final class AnswerValidator
{
    /**
     * @var list<FieldType>
     */
    private const array NOT_YET_SUPPORTED = [
        FieldType::Table,
    ];

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, int>  $evidenceCounts  archivos recibidos por campo
     * @return array<string, string|bool|list<string>>
     *
     * @throws InvalidAnswers
     */
    public function validate(FormSchema $schema, array $answers, array $evidenceCounts = []): array
    {
        $errores = [];
        $limpias = [];

        foreach ($schema->fields as $field) {
            if (! $field->type->isAnswerable()) {
                continue;
            }

            // Un campo oculto por su condicion ni se exige ni se guarda.
            if ($field->visibleWhen instanceof VisibilityCondition && ! $field->visibleWhen->isSatisfiedBy($answers)) {
                continue;
            }

            $clase = EvidenceKind::forFieldType($field->type);

            if ($clase instanceof EvidenceKind) {
                $recibidos = $evidenceCounts[$field->key] ?? 0;
                $maximo = $clase->maxPerField();

                if ($recibidos === 0 && $field->required) {
                    $errores[$field->key] = "«{$field->label}» es obligatorio.";
                } elseif ($recibidos > $maximo) {
                    $errores[$field->key] = "«{$field->label}» admite como mucho {$maximo} archivo(s).";
                } elseif ($recibidos > 0) {
                    $limpias[$field->key] = [];
                }

                continue;
            }

            $valor = $answers[$field->key] ?? null;
            $vacio = in_array($valor, [null, '', []], true);

            if (in_array($field->type, self::NOT_YET_SUPPORTED, true)) {
                if ($field->required) {
                    $errores[$field->key] = "«{$field->label}» es de tipo {$field->type->value}, que todavia no se puede completar.";
                }

                continue;
            }

            if ($vacio) {
                if ($field->required) {
                    $errores[$field->key] = "«{$field->label}» es obligatorio.";
                }

                continue;
            }

            try {
                $limpias[$field->key] = $this->normalize($field, $valor);
            } catch (InvalidAnswers $e) {
                $errores += $e->errors;
            }
        }

        if ($errores !== []) {
            throw new InvalidAnswers($errores);
        }

        return $limpias;
    }

    /**
     * @return string|bool|list<string>
     */
    private function normalize(Field $field, mixed $valor): string|bool|array
    {
        $falla = static fn (string $mensaje): InvalidAnswers => new InvalidAnswers([$field->key => "«{$field->label}» {$mensaje}"]);

        return match ($field->type) {
            FieldType::Text => is_scalar($valor) ? trim((string) $valor) : throw $falla('debe ser texto.'),

            FieldType::Number, FieldType::Money => $this->decimal($valor)
                ?? throw $falla('debe ser un numero, sin notacion cientifica.'),

            FieldType::Boolean => filter_var($valor, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                ?? throw $falla('debe ser si o no.'),

            FieldType::Date => $this->matchesFormat($valor, 'Y-m-d')
                ? (string) $valor
                : throw $falla('debe ser una fecha AAAA-MM-DD.'),

            FieldType::Time => $this->matchesFormat($valor, 'H:i')
                ? (string) $valor
                : throw $falla('debe ser una hora HH:MM.'),

            FieldType::Select => is_scalar($valor) && in_array((string) $valor, $field->options, true)
                ? (string) $valor
                : throw $falla('no es una de las opciones.'),

            FieldType::MultiSelect => $this->multiSelect($field, $valor) ?? throw $falla('contiene opciones que no existen.'),

            default => throw $falla('no se puede responder.'),
        };
    }

    /**
     * @return list<string>|null
     */
    private function multiSelect(Field $field, mixed $valor): ?array
    {
        if (! is_array($valor)) {
            return null;
        }

        $elegidas = [];

        foreach ($valor as $opcion) {
            if (! is_scalar($opcion) || ! in_array((string) $opcion, $field->options, true)) {
                return null;
            }

            $elegidas[] = (string) $opcion;
        }

        return array_values(array_unique($elegidas));
    }

    /**
     * Texto decimal canonico, sin pasar por float. Null si no es un decimal
     * simple.
     *
     * La notacion cientifica la acepta is_numeric() pero no se puede normalizar
     * sin float; se rechaza en vez de perder precision.
     */
    private function decimal(mixed $valor): ?string
    {
        if (! is_scalar($valor)) {
            return null;
        }

        $texto = trim((string) $valor);

        return preg_match('/^-?\d+(\.\d+)?$/', $texto) === 1 ? $texto : null;
    }

    private function matchesFormat(mixed $valor, string $formato): bool
    {
        if (! is_string($valor)) {
            return false;
        }

        try {
            return CarbonImmutable::createFromFormat($formato, $valor)?->format($formato) === $valor;
        } catch (Throwable) {
            return false;
        }
    }
}
