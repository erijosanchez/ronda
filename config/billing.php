<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Pasarela en uso
    |--------------------------------------------------------------------------
    | `manual` no cobra a nadie: anota la suscripcion y emite el comprobante
    | interno para que alguien cobre por transferencia. Es lo que corre hasta
    | que haya cuenta de comercio, y lo que se usa en las pruebas.
    |
    | `culqi` enciende el cobro de verdad. No hay nada mas que cambiar: las
    | Actions, las pantallas y el comando de renovacion son los mismos.
    |
    | Soportadas: "manual", "culqi"
    */

    'gateway' => env('BILLING_GATEWAY', 'manual'),

    /*
    |--------------------------------------------------------------------------
    | Moneda y ciclo por defecto
    |--------------------------------------------------------------------------
    | El precio se cobra por sede activa al mes (sec. 15.1). El ciclo anual
    | cobra diez meses en vez de doce: dos de descuento (sec. 3.6).
    */

    'currency' => env('BILLING_CURRENCY', 'PEN'),
    'cycle' => env('BILLING_CYCLE', 'monthly'),
    'yearly_free_months' => 2,

    /*
    |--------------------------------------------------------------------------
    | Prueba gratuita
    |--------------------------------------------------------------------------
    | 14 dias sin tarjeta (sec. 3.6). Mientras dura no se cobra ni se limita.
    */

    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Culqi
    |--------------------------------------------------------------------------
    | La llave publica va al navegador (formulario de tarjeta); la secreta no
    | sale del servidor. Mientras esten vacias, elegir `culqi` es un error de
    | configuracion y se dice en el acto en vez de fallar al primer cobro.
    |
    | Los montos viajan en CENTIMOS enteros: Culqi no acepta decimales, y es
    | justo donde un float se come un centavo.
    */

    'culqi' => [
        'public_key' => env('CULQI_PUBLIC_KEY'),
        'secret_key' => env('CULQI_SECRET_KEY'),
        'base_url' => env('CULQI_BASE_URL', 'https://api.culqi.com/v2'),
        'timeout' => (int) env('CULQI_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cobro con tarjeta: reintentos
    |--------------------------------------------------------------------------
    | Una tarjeta rechazada no es un cliente perdido: casi siempre es un limite
    | del dia o una tarjeta vencida. Se reintenta a los 3 y a los 7 dias, y
    | recien despues el cliente pasa a `past_due`.
    */

    'retry_days' => [3, 7],

];
