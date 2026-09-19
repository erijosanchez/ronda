<?php

declare(strict_types=1);

namespace Ronda\Platform\Application\Queries;

use Illuminate\Routing\UrlGenerator;
use Ronda\Platform\Domain\Models\Tenant;

/**
 * La direccion donde entra un cliente recien creado.
 * RONDA-PLAN-MAESTRO.md sec. 15.3
 *
 * Se arma desde el dominio CENTRAL —la pantalla de «preparando tu cuenta» vive
 * ahi—, asi que `route()` a secas devolveria la direccion equivocada: hay que
 * anteponer el dominio del cliente.
 *
 * El puerto se copia de `APP_URL` porque en desarrollo todo se sirve en :8000
 * y un enlace sin puerto no abre nada. En produccion no hay puerto que copiar y
 * la cadena queda igual.
 */
final readonly class TenantSignInUrlQuery
{
    public function __construct(
        private UrlGenerator $url,
    ) {}

    public function __invoke(Tenant $tenant): string
    {
        $ruta = $this->url->route('login', [], absolute: false);

        // Se consulta la columna y no la relacion porque el modelo de dominio
        // lo aporta stancl/tenancy a traves de un contrato que no declara
        // `domain`: leerlo como propiedad no lo entiende ni phpstan ni nadie.
        $dominio = $tenant->domains()->value('domain');

        if (! is_string($dominio)) {
            return $this->url->to($ruta);
        }

        $base = parse_url((string) config('app.url'));
        $esquema = is_array($base) && isset($base['scheme']) && is_string($base['scheme'])
            ? $base['scheme']
            : 'http';
        $puerto = is_array($base) && isset($base['port']) && is_int($base['port'])
            ? ':'.$base['port']
            : '';

        return $esquema.'://'.$dominio.$puerto.$ruta;
    }
}
