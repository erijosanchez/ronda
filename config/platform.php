<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Dominio donde cuelgan los clientes
    |--------------------------------------------------------------------------
    | Quien se registra elige un subdominio y el alta lo convierte en el
    | dominio completo del cliente: `acme` + `ronda.pe` -> `acme.ronda.pe`.
    | En desarrollo es `localhost`, que acepta subdominios sin tocar el DNS.
    */

    'domain' => env('CENTRAL_DOMAIN', 'localhost'),

    /*
    |--------------------------------------------------------------------------
    | Registro self-service
    |--------------------------------------------------------------------------
    | `enabled` en false devuelve 404 en /registro sin quitar la ruta: durante
    | el piloto las altas las hacemos nosotros, y se abre cuando toque.
    |
    | Cada alta crea una base de datos, asi que el limite por hora y por IP no
    | es cortesia: es lo que impide que alguien llene el servidor con un bucle.
    */

    'registration' => [
        'enabled' => (bool) env('REGISTRATION_ENABLED', true),
        'per_hour' => (int) env('REGISTRATION_PER_HOUR', 5),
    ],

];
