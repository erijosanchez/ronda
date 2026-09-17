<?php

declare(strict_types=1);

use Ronda\Forms\Domain\ValueObjects\FormSchema;
use Ronda\Submissions\Domain\Exceptions\InvalidAnswers;
use Ronda\Submissions\Domain\Services\AnswerValidator;
use Ronda\Submissions\Domain\Services\ReportableValues;

// Validacion de respuestas contra una version del formulario. ADR 0012.
//
// Lo que entra en `submissions.data` es la fuente de verdad: si esta validacion
// deja pasar basura, la basura se queda para siempre.

function arqueo(): FormSchema
{
    return FormSchema::fromArray([
        ['key' => 'monto', 'type' => 'money', 'label' => 'Monto', 'required' => true, 'reportable' => true],
        ['key' => 'hubo_faltante', 'type' => 'boolean', 'label' => 'Hubo faltante', 'reportable' => true],
        [
            'key' => 'monto_faltante', 'type' => 'money', 'label' => 'Monto del faltante', 'required' => true,
            'visible_when' => ['field' => 'hubo_faltante', 'operator' => 'equals', 'value' => true],
        ],
        ['key' => 'turno', 'type' => 'select', 'label' => 'Turno', 'options' => ['manana', 'tarde'], 'reportable' => true],
        ['key' => 'medios', 'type' => 'multi_select', 'label' => 'Medios', 'options' => ['efectivo', 'tarjeta', 'yape']],
        ['key' => 'fecha_deposito', 'type' => 'date', 'label' => 'Fecha de deposito', 'reportable' => true],
        ['key' => 'notas', 'type' => 'text', 'label' => 'Notas'],
    ]);
}

/**
 * @param  array<string, mixed>  $answers
 * @return array<string, string>
 */
function erroresDe(FormSchema $schema, array $answers): array
{
    try {
        (new AnswerValidator)->validate($schema, $answers);
    } catch (InvalidAnswers $e) {
        return $e->errors;
    }

    return [];
}

it('acepta respuestas validas y las normaliza', function (): void {
    $limpias = (new AnswerValidator)->validate(arqueo(), [
        'monto' => ' 1234.10 ',
        'hubo_faltante' => '0',
        'turno' => 'tarde',
        'medios' => ['efectivo', 'yape', 'efectivo'],
        'fecha_deposito' => '2026-09-15',
        'notas' => '  todo en orden ',
    ]);

    expect($limpias)->toBe([
        'monto' => '1234.10',            // texto decimal, no float
        'hubo_faltante' => false,
        'turno' => 'tarde',
        'medios' => ['efectivo', 'yape'], // sin duplicados
        'fecha_deposito' => '2026-09-15',
        'notas' => 'todo en orden',
    ]);
});

it('conserva el importe exacto sin pasar por float', function (): void {
    // 0.1 + 0.2 en float no es 0.3. Un arqueo tiene que cuadrar al centimo.
    $limpias = (new AnswerValidator)->validate(arqueo(), ['monto' => '1234567890.12']);

    expect($limpias['monto'])->toBe('1234567890.12');
});

it('exige los campos obligatorios', function (): void {
    expect(erroresDe(arqueo(), []))->toHaveKey('monto');
});

it('no exige un campo obligatorio que esta oculto', function (): void {
    // «Monto del faltante» solo se pide si hubo faltante.
    $errores = erroresDe(arqueo(), ['monto' => '100', 'hubo_faltante' => '0']);

    expect($errores)->not->toHaveKey('monto_faltante');
});

it('exige un campo obligatorio en cuanto su condicion lo hace visible', function (): void {
    // Contraste con la prueba anterior: los mismos datos cambiando una respuesta.
    $errores = erroresDe(arqueo(), ['monto' => '100', 'hubo_faltante' => '1']);

    expect($errores)->toHaveKey('monto_faltante');
});

it('descarta lo oculto y lo que no pertenece al formulario', function (): void {
    $limpias = (new AnswerValidator)->validate(arqueo(), [
        'monto' => '100',
        'hubo_faltante' => '0',
        'monto_faltante' => '50',       // oculto: no se guarda
        'campo_inventado' => 'x',       // no existe: no se guarda
    ]);

    expect($limpias)->not->toHaveKey('monto_faltante')
        ->and($limpias)->not->toHaveKey('campo_inventado');
});

it('rechaza una opcion que no existe', function (): void {
    expect(erroresDe(arqueo(), ['monto' => '1', 'turno' => 'madrugada']))->toHaveKey('turno')
        ->and(erroresDe(arqueo(), ['monto' => '1', 'medios' => ['efectivo', 'bitcoin']]))->toHaveKey('medios');
});

it('rechaza numeros mal formados y notacion cientifica', function (): void {
    expect(erroresDe(arqueo(), ['monto' => 'mil']))->toHaveKey('monto')
        ->and(erroresDe(arqueo(), ['monto' => '1e3']))->toHaveKey('monto');
});

it('rechaza fechas imposibles', function (): void {
    expect(erroresDe(arqueo(), ['monto' => '1', 'fecha_deposito' => '2026-02-30']))->toHaveKey('fecha_deposito');
});

it('bloquea el envio si falta una foto obligatoria que aun no se puede subir', function (): void {
    // Aceptar un arqueo sin la foto que el cliente exigio es peor que no dejar
    // enviarlo.
    $schema = FormSchema::fromArray([
        ['key' => 'foto_caja', 'type' => 'photo', 'label' => 'Foto de la caja', 'required' => true],
    ]);

    expect(erroresDe($schema, []))->toHaveKey('foto_caja');
});

it('ignora una foto opcional que aun no se puede subir', function (): void {
    $schema = FormSchema::fromArray([
        ['key' => 'notas', 'type' => 'text', 'label' => 'Notas'],
        ['key' => 'foto', 'type' => 'photo', 'label' => 'Foto'],
    ]);

    expect(erroresDe($schema, ['notas' => 'ok']))->toBe([]);
});

it('replica solo lo reportable, cada valor en su columna', function (): void {
    $limpias = (new AnswerValidator)->validate(arqueo(), [
        'monto' => '1500.50',
        'hubo_faltante' => '0',
        'turno' => 'manana',
        'fecha_deposito' => '2026-09-16',
        'notas' => 'no es reportable',
    ]);

    $filas = (new ReportableValues)->extract(arqueo(), $limpias);
    $porClave = array_column($filas, null, 'field_key');

    expect(array_keys($porClave))->toBe(['monto', 'hubo_faltante', 'turno', 'fecha_deposito'])
        ->and($porClave['monto']['value_numeric'])->toBe('1500.50')
        ->and($porClave['monto']['value_text'])->toBeNull()
        ->and($porClave['hubo_faltante']['value_bool'])->toBeFalse()
        ->and($porClave['turno']['value_text'])->toBe('manana')
        ->and($porClave['fecha_deposito']['value_date'])->toBe('2026-09-16');
});
