<?php

declare(strict_types=1);

/**
 * Textos de las importaciones por CSV. RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * Las claves de `columns` son las cabeceras normalizadas del archivo, no
 * nombres internos: lo que se le dice al usuario tiene que ser la palabra que
 * el escribio en su hoja.
 */
return [

    'columns' => [
        'codigo' => 'código',
        'nombre' => 'nombre',
        'zona' => 'zona',
        'direccion' => 'dirección',
        'latitud' => 'latitud',
        'longitud' => 'longitud',
        'zona_horaria' => 'zona horaria',
        'abre' => 'abre',
        'cierra' => 'cierra',
        'correo' => 'correo',
        'telefono' => 'teléfono',
        'roles' => 'roles',
        'estado' => 'estado',
        'sedes' => 'sedes',
    ],

    'issues' => [
        'required' => 'Falta «:column».',
        'invalid' => '«:value» no vale como :column.',
        'too_long' => '«:value» es demasiado largo para :column.',
        'duplicated' => '«:value» se repite en el archivo.',
        'not_found' => '«:value» no existe todavía en Ronda.',
        'taken' => '«:value» ya está en uso y esta importación no puede tocarlo.',
        'not_allowed' => '«:value» no se puede asignar importando.',
    ],

];
