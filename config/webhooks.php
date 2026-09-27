<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Webhooks salientes (sec. 13.2)
    |--------------------------------------------------------------------------
    | `allow_insecure` deja usar `http` y direcciones privadas. Solo para
    | desarrollo y para las pruebas: en un servidor de verdad seria la puerta
    | abierta al SSRF que el resto del codigo se dedica a cerrar.
    */

    'allow_insecure' => (bool) env('WEBHOOKS_ALLOW_INSECURE', false),

    /*
    | Espera maxima por destino. Corta a proposito: un servidor lento del
    | cliente no puede retener a un trabajador de la cola.
    */

    'timeout' => (int) env('WEBHOOKS_TIMEOUT', 10),

];
