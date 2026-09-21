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

    /*
    |--------------------------------------------------------------------------
    | Suplantacion (sec. 15.4)
    |--------------------------------------------------------------------------
    | Entrar a la cuenta de un cliente para darle soporte tiene limite de
    | tiempo: media hora alcanza para ver lo que pasa y no alcanza para
    | olvidarse abierto. Al vencer, la sesion se cierra sola.
    |
    | El motivo es obligatorio y tiene un minimo de longitud: «revisar» no
    | explica nada, y el registro existe para que el cliente pueda leerlo.
    */

    'impersonation' => [
        'minutes' => (int) env('IMPERSONATION_MINUTES', 30),
        'min_reason' => (int) env('IMPERSONATION_MIN_REASON', 15),
    ],

];
