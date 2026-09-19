<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Pennant Store
    |--------------------------------------------------------------------------
    |
    | Here you will specify the default store that Pennant should use when
    | storing and resolving feature flag values. Pennant ships with the
    | ability to store flag values in an in-memory array or database.
    |
    | Supported: "array", "database"
    |
    */

    /*
    | En Ronda las banderas SE DERIVAN DEL PLAN (sec. 3.6), no se guardan: con
    | el almacen `database`, pennant persiste el valor la primera vez que lo
    | resuelve, y un cliente que sube a Pro seguiria con las funciones apagadas
    | hasta que alguien purgara la tabla. Con `array` se resuelven en cada
    | peticion contra el plan que tiene ahora.
    |
    | Las sobreescrituras por cliente del §8.2 (`feature_flags`) llegan con el
    | back-office: ahi si hace falta persistirlas, y entonces esto pasa a
    | `database` con una purga al cambiar de plan.
    */

    'default' => env('PENNANT_STORE', 'array'),

    /*
    |--------------------------------------------------------------------------
    | Pennant Stores
    |--------------------------------------------------------------------------
    |
    | Here you may configure each of the stores that should be available to
    | Pennant. These stores shall be used to store resolved feature flag
    | values - you may configure as many as your application requires.
    |
    */

    'stores' => [

        'array' => [
            'driver' => 'array',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => null,
            'table' => 'features',
        ],

    ],
];
