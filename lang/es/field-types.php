<?php

declare(strict_types=1);

// Tipos de campo del disenador. RONDA-PLAN-MAESTRO.md sec. 9.2
// Las claves son los valores de Ronda\Forms\Domain\ValueObjects\FieldType.
return [
    'text' => 'Texto',
    'number' => 'Número',
    'money' => 'Importe',
    'date' => 'Fecha',
    'time' => 'Hora',
    'boolean' => 'Sí / No',
    'select' => 'Selección única',
    'multi_select' => 'Selección múltiple',
    'photo' => 'Foto',
    'file' => 'Archivo',
    'signature' => 'Firma',
    'table' => 'Tabla de filas',
    'calculated' => 'Campo calculado',
    'section' => 'Sección',
];
