<?php

declare(strict_types=1);

use Ronda\Forms\Domain\Exceptions\InvalidFormSchema;
use Ronda\Forms\Domain\ValueObjects\ComparisonOperator;
use Ronda\Forms\Domain\ValueObjects\FieldType;
use Ronda\Forms\Domain\ValueObjects\FormSchema;

// La integridad del esquema. Ver docs/adr/0012.
//
// PostgreSQL no valida el contenido de una columna JSONB, asi que estas reglas
// son la UNICA barrera entre un formulario incoherente y la base de datos.
// Sin base de datos de por medio: son objetos de dominio puros.

it('construye un esquema valido', function (): void {
    $schema = FormSchema::fromArray([
        ['key' => 'monto', 'type' => 'money', 'label' => 'Monto', 'reportable' => true],
        ['key' => 'turno', 'type' => 'select', 'label' => 'Turno', 'options' => ['manana', 'tarde']],
    ]);

    expect($schema->count())->toBe(2)
        ->and($schema->field('monto')?->type)->toBe(FieldType::Money)
        ->and($schema->field('turno')?->options)->toBe(['manana', 'tarde'])
        ->and($schema->field('inexistente'))->toBeNull();
});

it('rechaza un esquema vacio', function (): void {
    expect(fn (): FormSchema => FormSchema::fromArray([]))
        ->toThrow(InvalidFormSchema::class);
});

it('rechaza claves repetidas', function (): void {
    // Dos campos con la misma clave significa que una respuesta pisa a la otra.
    expect(fn (): FormSchema => FormSchema::fromArray([
        ['key' => 'monto', 'type' => 'money'],
        ['key' => 'monto', 'type' => 'text'],
    ]))->toThrow(InvalidFormSchema::class, 'repetida');
});

it('rechaza un campo sin clave y dice donde esta', function (): void {
    expect(fn (): FormSchema => FormSchema::fromArray([
        ['key' => 'monto', 'type' => 'money'],
        ['type' => 'text'],
    ]))->toThrow(InvalidFormSchema::class, 'posicion 1');
});

it('rechaza un tipo desconocido', function (): void {
    expect(fn (): FormSchema => FormSchema::fromArray([
        ['key' => 'raro', 'type' => 'holograma'],
    ]))->toThrow(InvalidFormSchema::class, 'holograma');
});

it('exige opciones en los campos de seleccion', function (): void {
    expect(fn (): FormSchema => FormSchema::fromArray([
        ['key' => 'turno', 'type' => 'select'],
    ]))->toThrow(InvalidFormSchema::class, 'no declara opciones');
});

it('rechaza opciones en un campo que no las admite', function (): void {
    expect(fn (): FormSchema => FormSchema::fromArray([
        ['key' => 'monto', 'type' => 'money', 'options' => ['a', 'b']],
    ]))->toThrow(InvalidFormSchema::class, 'no admite opciones');
});

it('no deja exigir un campo que el usuario no rellena', function (): void {
    // Una seccion es texto fijo: marcarla obligatoria bloquearia el envio para
    // siempre, sin que nadie entienda por que.
    expect(fn (): FormSchema => FormSchema::fromArray([
        ['key' => 'aviso', 'type' => 'section', 'required' => true],
    ]))->toThrow(InvalidFormSchema::class, 'no lo rellena el usuario');
});

it('no deja marcar como reportable lo que no tiene valor escalar', function (): void {
    // Una firma no se puede indexar ni promediar: su sitio es el JSONB.
    expect(fn (): FormSchema => FormSchema::fromArray([
        ['key' => 'firma', 'type' => 'signature', 'reportable' => true],
    ]))->toThrow(InvalidFormSchema::class, 'reportable');
});

it('acepta una condicion de visibilidad sobre otro campo', function (): void {
    $schema = FormSchema::fromArray([
        ['key' => 'hubo_faltante', 'type' => 'boolean', 'label' => 'Hubo faltante'],
        [
            'key' => 'monto_faltante',
            'type' => 'money',
            'label' => 'Monto del faltante',
            'visible_when' => ['field' => 'hubo_faltante', 'operator' => 'equals', 'value' => true],
        ],
    ]);

    $condicion = $schema->field('monto_faltante')?->visibleWhen;

    expect($condicion?->field)->toBe('hubo_faltante')
        ->and($condicion?->operator)->toBe(ComparisonOperator::Equals)
        ->and($condicion?->value)->toBeTrue();
});

it('rechaza una condicion que apunta a un campo inexistente', function (): void {
    // Nunca se evaluaria, asi que el campo quedaria invisible para siempre.
    expect(fn (): FormSchema => FormSchema::fromArray([
        [
            'key' => 'monto',
            'type' => 'money',
            'visible_when' => ['field' => 'fantasma', 'operator' => 'equals', 'value' => 1],
        ],
    ]))->toThrow(InvalidFormSchema::class, 'fantasma');
});

it('rechaza un campo que depende de si mismo', function (): void {
    expect(fn (): FormSchema => FormSchema::fromArray([
        [
            'key' => 'monto',
            'type' => 'money',
            'visible_when' => ['field' => 'monto', 'operator' => 'equals', 'value' => 1],
        ],
    ]))->toThrow(InvalidFormSchema::class, 'si mismo');
});

it('rechaza un operador desconocido', function (): void {
    expect(fn (): FormSchema => FormSchema::fromArray([
        ['key' => 'a', 'type' => 'text'],
        ['key' => 'b', 'type' => 'text', 'visible_when' => ['field' => 'a', 'operator' => 'parecido', 'value' => 'x']],
    ]))->toThrow(InvalidFormSchema::class, 'parecido');
});

it('sobrevive a la ida y vuelta a array', function (): void {
    // Es como viaja al JSONB y como vuelve: si el ciclo pierde algo, una
    // version publicada deja de leerse igual que cuando se guardo.
    $original = [
        ['key' => 'hubo_faltante', 'type' => 'boolean', 'label' => 'Hubo faltante', 'required' => true],
        [
            'key' => 'monto',
            'type' => 'money',
            'label' => 'Monto',
            'help' => 'En soles',
            'reportable' => true,
            'rules' => ['min' => 0],
            'visible_when' => ['field' => 'hubo_faltante', 'operator' => 'equals', 'value' => true],
        ],
    ];

    $ida = FormSchema::fromArray($original)->toArray();
    $vuelta = FormSchema::fromArray($ida)->toArray();

    expect($vuelta)->toBe($ida)
        ->and($ida[1]['visible_when'])->toBe($original[1]['visible_when'])
        ->and($ida[1]['help'])->toBe('En soles');
});

it('clasifica cada tipo de campo', function (): void {
    // Los catorce tipos de la v1 (sec. 9.2).
    expect(FieldType::cases())->toHaveCount(14);

    expect(FieldType::Select->hasOptions())->toBeTrue()
        ->and(FieldType::Text->hasOptions())->toBeFalse()
        ->and(FieldType::Section->isAnswerable())->toBeFalse()
        ->and(FieldType::Calculated->isAnswerable())->toBeFalse()
        ->and(FieldType::Money->isReportable())->toBeTrue()
        ->and(FieldType::Photo->isReportable())->toBeFalse();
});
