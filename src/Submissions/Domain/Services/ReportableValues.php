<?php

declare(strict_types=1);

namespace Ronda\Submissions\Domain\Services;

use Ronda\Forms\Domain\ValueObjects\FieldType;
use Ronda\Forms\Domain\ValueObjects\FormSchema;

/**
 * Traduce las respuestas reportables a filas tipadas de `submission_values`.
 * ADR 0012.
 *
 * Solo los campos marcados como reportables, y cada valor en UNA columna segun
 * su tipo. Es lo que alimenta filtros y KPI sin tocar el JSONB.
 *
 * Una seleccion multiple se guarda como texto con las opciones unidas por «|».
 * Sirve para filtrar por contenido; si algun KPI necesita contarlas una a una,
 * hara falta una fila por opcion, y eso es un cambio de esta clase, no del
 * esquema.
 */
final class ReportableValues
{
    /**
     * @param  array<string, string|bool|list<string>>  $answers  ya validadas
     * @return list<array{field_key: string, value_text: string|null, value_numeric: string|null, value_date: string|null, value_bool: bool|null}>
     */
    public function extract(FormSchema $schema, array $answers): array
    {
        $filas = [];

        foreach ($schema->reportableFields() as $field) {
            if (! array_key_exists($field->key, $answers)) {
                continue;
            }

            $valor = $answers[$field->key];

            $fila = [
                'field_key' => $field->key,
                'value_text' => null,
                'value_numeric' => null,
                'value_date' => null,
                'value_bool' => null,
            ];

            match ($field->type) {
                FieldType::Number, FieldType::Money => $fila['value_numeric'] = (string) (is_array($valor) ? '' : $valor),
                FieldType::Date => $fila['value_date'] = (string) (is_array($valor) ? '' : $valor),
                FieldType::Boolean => $fila['value_bool'] = (bool) $valor,
                FieldType::MultiSelect => $fila['value_text'] = implode('|', is_array($valor) ? $valor : [(string) $valor]),
                default => $fila['value_text'] = is_array($valor) ? implode('|', $valor) : (string) $valor,
            };

            $filas[] = $fila;
        }

        return $filas;
    }
}
