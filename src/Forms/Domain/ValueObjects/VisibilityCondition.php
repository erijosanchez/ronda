<?php

declare(strict_types=1);

namespace Ronda\Forms\Domain\ValueObjects;

use Ronda\Forms\Domain\Exceptions\InvalidFormSchema;

/**
 * «Mostrar este campo si el campo X vale Y». RONDA-PLAN-MAESTRO.md sec. 9.2
 *
 * Deliberadamente simple: una condicion sobre UN campo. Un motor de expresiones
 * con `y`/`o` anidados es facil de escribir y dificil de explicar a quien
 * disena el formulario; si hace falta, se amplia cuando alguien lo pida de
 * verdad.
 */
final readonly class VisibilityCondition
{
    /**
     * @param  string  $field  clave del campo del que depende
     * @param  scalar|list<scalar>  $value
     */
    private function __construct(
        public string $field,
        public ComparisonOperator $operator,
        public string|int|float|bool|array $value,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw, string $fieldKey): self
    {
        $dependsOn = $raw['field'] ?? null;

        if (! is_string($dependsOn) || $dependsOn === '') {
            throw InvalidFormSchema::conditionWithoutField($fieldKey);
        }

        $operator = ComparisonOperator::tryFrom((string) ($raw['operator'] ?? ''));

        if (! $operator instanceof ComparisonOperator) {
            throw InvalidFormSchema::unknownOperator($fieldKey, (string) ($raw['operator'] ?? ''));
        }

        /** @var scalar|list<scalar> $value */
        $value = $raw['value'] ?? null;

        return new self($dependsOn, $operator, $value);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'operator' => $this->operator->value,
            'value' => $this->value,
        ];
    }
}
