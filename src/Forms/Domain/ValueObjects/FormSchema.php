<?php

declare(strict_types=1);

namespace Ronda\Forms\Domain\ValueObjects;

use Ronda\Forms\Domain\Exceptions\InvalidFormSchema;

/**
 * El esquema completo de una version de plantilla. Ver docs/adr/0012.
 *
 * Es lo que se guarda en `template_versions.schema`, y la unica puerta por la
 * que entra: PostgreSQL no valida el contenido de una columna JSONB, asi que la
 * integridad la sostiene esta clase.
 *
 * Las coherencias de un campo consigo mismo las comprueba Field; aqui se
 * comprueban las que solo se ven con el esquema entero delante: claves
 * repetidas y condiciones que apuntan a campos inexistentes.
 */
final readonly class FormSchema
{
    /**
     * @param  list<Field>  $fields
     */
    private function __construct(
        public array $fields,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $raw
     */
    public static function fromArray(array $raw): self
    {
        if ($raw === []) {
            throw InvalidFormSchema::emptySchema();
        }

        $fields = [];
        $keys = [];

        foreach (array_values($raw) as $position => $definition) {
            $field = Field::fromArray($definition, $position);

            if (in_array($field->key, $keys, true)) {
                throw InvalidFormSchema::duplicateKey($field->key);
            }

            $keys[] = $field->key;
            $fields[] = $field;
        }

        self::guardConditions($fields, $keys);

        return new self($fields);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (Field $field): array => $field->toArray(),
            $this->fields,
        );
    }

    /**
     * Campos que se replican en `submission_values` (ADR 0012).
     *
     * @return list<Field>
     */
    public function reportableFields(): array
    {
        return array_values(array_filter(
            $this->fields,
            static fn (Field $field): bool => $field->reportable,
        ));
    }

    public function field(string $key): ?Field
    {
        foreach ($this->fields as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        return null;
    }

    public function count(): int
    {
        return count($this->fields);
    }

    /**
     * @param  list<Field>  $fields
     * @param  list<string>  $keys
     */
    private static function guardConditions(array $fields, array $keys): void
    {
        foreach ($fields as $field) {
            $condition = $field->visibleWhen;

            if (! $condition instanceof VisibilityCondition) {
                continue;
            }

            // Una condicion que apunta a un campo inexistente no se evalua
            // nunca, asi que el campo quedaria invisible para siempre sin que
            // nadie entienda por que.
            if (! in_array($condition->field, $keys, true)) {
                throw InvalidFormSchema::conditionOnMissingField($field->key, $condition->field);
            }

            if ($condition->field === $field->key) {
                throw InvalidFormSchema::conditionOnItself($field->key);
            }
        }
    }
}
