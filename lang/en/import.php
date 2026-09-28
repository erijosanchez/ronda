<?php

declare(strict_types=1);

/**
 * CSV import wording. RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * The `columns` keys are the file's normalised headers, in Spanish, because
 * that is what the person typed into their spreadsheet.
 */
return [

    'columns' => [
        'codigo' => 'code',
        'nombre' => 'name',
        'zona' => 'zone',
        'direccion' => 'address',
        'latitud' => 'latitude',
        'longitud' => 'longitude',
        'zona_horaria' => 'time zone',
        'abre' => 'opens at',
        'cierra' => 'closes at',
        'correo' => 'email',
        'telefono' => 'phone',
        'roles' => 'roles',
        'estado' => 'status',
        'sedes' => 'sites',
    ],

    'issues' => [
        'required' => '«:column» is missing.',
        'invalid' => '«:value» is not a valid :column.',
        'too_long' => '«:value» is too long for :column.',
        'duplicated' => '«:value» appears twice in the file.',
        'not_found' => '«:value» does not exist in Ronda yet.',
        'taken' => '«:value» is already in use and this import cannot touch it.',
        'not_allowed' => '«:value» cannot be granted by importing.',
    ],

];
