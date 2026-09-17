<?php

declare(strict_types=1);

namespace Ronda\Notifications\Application\Queries;

use Illuminate\Routing\UrlGenerator;

/**
 * La direccion absoluta de una pantalla EN EL DOMINIO DEL CLIENTE.
 *
 * Los avisos se arman en un job, sin peticion. `route()` usaria entonces
 * `APP_URL`, que es el dominio central: el correo de un incumplimiento llevaria
 * a la portada del SaaS en vez de a la sede. Aqui se antepone el dominio del
 * tenant en curso.
 */
final readonly class TenantUrlQuery
{
    public function __construct(
        private UrlGenerator $url,
    ) {}

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function __invoke(string $routeName, array $parameters = []): string
    {
        $ruta = $this->url->route($routeName, $parameters, absolute: false);

        $tenant = tenant();
        $dominio = $tenant?->domains->first()?->domain;

        if ($dominio === null) {
            return $this->url->to($ruta);
        }

        $esquema = str_starts_with((string) config('app.url'), 'https') ? 'https' : 'http';

        return $esquema.'://'.$dominio.$ruta;
    }
}
