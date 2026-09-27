<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cuota de la API (sec. 13.1)
    |--------------------------------------------------------------------------
    | Peticiones por minuto y por TOKEN, no por IP: varias integraciones del
    | mismo cliente salen de la misma direccion, y a veces de la misma nube que
    | las de otro cliente.
    */

    'rate_limit_per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 120),

    /*
    |--------------------------------------------------------------------------
    | Tamano de pagina
    |--------------------------------------------------------------------------
    | Las colecciones van por cursor (sec. 13.1): en una tabla de envios de un
    | ano, pedir la pagina 900 con OFFSET obliga a PostgreSQL a recorrer todo lo
    | anterior. El cursor no.
    */

    'per_page' => (int) env('API_PER_PAGE', 50),
    'max_per_page' => (int) env('API_MAX_PER_PAGE', 200),

];
