<?php

declare(strict_types=1);

namespace Ronda\Forms\Domain\ValueObjects;

use Ronda\Forms\Domain\Exceptions\InvalidFormSchema;

/**
 * Un campo de una plantilla. RONDA-PLAN-MAESTRO.md sec. 9.2
 *
 * Inmutable: forma parte de una version publicada, que no se modifica nunca
 * (ADR 0012).
 *
 * Valida en el constructor. PostgreSQL no comprueba el contenido de una columna
 * JSONB, asi que si un campo incoherente puede construirse, acaba guardado.
 */
final readonly class Field
{
    /**
     * @param  list<string>  $options
     * @param  array<string, mixed>  $rules
     */
    private function __construct(
        public string $key,
        public FieldType $type,
        public string $label,
        public ?string $help,
        public bool $required,
        public bool $reportable,
        public array $options,
        public array $rules,
        public ?VisibilityCondition $visibleWhen,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw, int $position): self
    {
        $key = $raw['key'] ?? null;

        if (! is_string($key) || trim($key) === '') {
            throw InvalidFormSchema::fieldWithoutKey($position);
        }

        $type = FieldType::tryFrom((string) ($raw['type'] ?? ''));

        if (! $type instanceof FieldType) {
            throw InvalidFormSchema::unknownType($key, (string) ($raw['type'] ?? ''));
        }

        /** @var list<string> $options */
        $options = array_values(array_map(
            static fn (mixed $option): string => (string) $option,
            is_array($raw['options'] ?? null) ? $raw['options'] : [],
        ));

        $required = (bool) ($raw['required'] ?? false);
        $reportable = (bool) ($raw['reportable'] ?? false);

        self::guard($key, $type, $options, $required, $reportable);

        $condition = is_array($raw['visible_when'] ?? null)
            ? VisibilityCondition::fromArray($raw['visible_when'], $key)
            : null;

        /** @var array<string, mixed> $rules */
        $rules = is_array($raw['rules'] ?? null) ? $raw['rules'] : [];

        return new self(
            key: $key,
            type: $type,
            label: (string) ($raw['label'] ?? $key),
            help: isset($raw['help']) ? (string) $raw['help'] : null,
            required: $required,
            reportable: $reportable,
            options: $options,
            rules: $rules,
            visibleWhen: $condition,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'key' => $this->key,
            'type' => $this->type->value,
            'label' => $this->label,
            'help' => $this->help,
            'required' => $this->required,
            'reportable' => $this->reportable,
            'options' => $this->options,
            'rules' => $this->rules,
            'visible_when' => $this->visibleWhen?->toArray(),
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * Coherencias que el tipo impone y que el disenador no deberia poder
     * saltarse.
     *
     * @param  list<string>  $options
     */
    private static function guard(
        string $key,
        FieldType $type,
        array $options,
        bool $required,
        bool $reportable,
    ): void {
        if ($type->hasOptions() && $options === []) {
            throw InvalidFormSchema::optionsRequired($key);
        }

        if (! $type->hasOptions() && $options !== []) {
            throw InvalidFormSchema::optionsNotAllowed($key);
        }

        // Una seccion es texto fijo y un calculado lo deriva el motor: exigir
        // que se rellenen bloquearia el envio para siempre.
        if ($required && ! $type->isAnswerable()) {
            throw InvalidFormSchema::cannotBeRequired($key);
        }

        // Sin valor escalar no hay nada que replicar en submission_values.
        if ($reportable && ! $type->isReportable()) {
            throw InvalidFormSchema::cannotBeReportable($key);
        }
    }
}
