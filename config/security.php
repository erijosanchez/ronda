<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cabeceras de seguridad
    |--------------------------------------------------------------------------
    | Se desactivan solo para depurar en local. En cualquier entorno desplegado
    | van activas. Ver RONDA-PLAN-MAESTRO.md sec. 10.5
    */

    'headers_enabled' => env('SECURITY_HEADERS_ENABLED', true),
    'csp_enabled' => env('CSP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Politica de contrasenas
    |--------------------------------------------------------------------------
    | Alineada con NIST 800-63B: longitud por encima de complejidad arbitraria,
    | y comprobacion contra filtraciones conocidas. Ver sec. 10.2
    */

    'password' => [
        'min_length' => (int) env('PASSWORD_MIN_LENGTH', 12),
        'check_compromised' => (bool) env('PASSWORD_CHECK_COMPROMISED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Doble factor
    |--------------------------------------------------------------------------
    | Roles que no pueden operar sin 2FA configurado.
    */

    'two_factor' => [
        'enforced_roles' => array_filter(
            explode(',', (string) env('TWO_FACTOR_ENFORCED_ROLES', 'owner,admin,finance')),
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Evidencia
    |--------------------------------------------------------------------------
    | Vida de la URL firmada, en minutos. Corta a proposito: la URL se comparte
    | por WhatsApp con demasiada facilidad.
    */

    'evidence' => [
        'signed_url_ttl' => (int) env('EVIDENCE_SIGNED_URL_TTL', 5),
    ],

];
