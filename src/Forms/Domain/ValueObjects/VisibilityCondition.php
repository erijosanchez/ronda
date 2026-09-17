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
     * Si la condicion se cumple con estas respuestas.
     *
     * Un campo oculto no se valida ni se guarda: marcar obligatorio «monto del
     * faltante» solo tiene sentido si antes se contesto que hubo faltante.
     *
     * @param  array<string, mixed>  $answers
     */
    public function isSatisfiedBy(array $answers): bool
    {
        $actual = $answers[$this->field] ?? null;
        $vacio = in_array($actual, [null, '', []], true);

        return match ($this->operator) {
            ComparisonOperator::IsEmpty => $vacio,
            ComparisonOperator::IsNotEmpty => ! $vacio,
            // Comparacion laxa a proposito: del formulario llega «1» y la
            // condicion puede decir 1 o true.
            ComparisonOperator::Equals => ! $vacio && $this->sameValue($actual, $this->value),
            ComparisonOperator::NotEquals => $vacio || ! $this->sameValue($actual, $this->value),
            ComparisonOperator::In => ! $vacio && is_array($this->value)
                && array_filter($this->value, fn (mixed $v): bool => $this->sameValue($actual, $v)) !== [],
            ComparisonOperator::GreaterThan => is_numeric($actual) && is_numeric($this->value) && (float) $actual > (float) $this->value,
            ComparisonOperator::LessThan => is_numeric($actual) && is_numeric($this->value) && (float) $actual < (float) $this->value,
        };
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

    private function sameValue(mixed $actual, mixed $esperado): bool
    {
        if (is_bool($esperado)) {
            return filter_var($actual, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === $esperado;
        }

        if (is_numeric($actual) && is_numeric($esperado)) {
            return (float) $actual === (float) $esperado;
        }

        return (string) (is_scalar($actual) ? $actual : '') === (string) (is_scalar($esperado) ? $esperado : '');
    }
}
