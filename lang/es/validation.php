<?php

declare(strict_types=1);

// Solo los mensajes que la aplicacion usa de verdad. El resto lo cubre
// el paquete de traducciones de Laravel cuando se instale.
return [
    'required' => 'El campo :attribute es obligatorio.',
    'email' => 'El campo :attribute debe ser un correo válido.',
    'min' => [
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
    ],
    'max' => [
        'string' => 'El campo :attribute no puede tener más de :max caracteres.',
        'file' => 'El archivo :attribute no puede pesar más de :max kilobytes.',
    ],
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'unique' => 'El valor de :attribute ya está registrado.',
    'mimes' => 'El archivo :attribute debe ser de tipo: :values.',
    'uncompromised' => 'Esta contraseña apareció en una filtración de datos. Elige otra.',

    'attributes' => [
        'email' => 'correo',
        'password' => 'contraseña',
        'name' => 'nombre',
        'site_id' => 'sede',
    ],
];
