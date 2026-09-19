<?php

declare(strict_types=1);

namespace Ronda\Platform\Application\Actions;

use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Exceptions\SubdomainUnavailable;
use Ronda\Platform\Domain\Models\Tenant;
use Ronda\Platform\Domain\ReservedSubdomains;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * Da de alta un cliente que se registro solo. RONDA-PLAN-MAESTRO.md sec. 15.3
 *
 * La diferencia con CreateTenant es quien lo pide: alli lo pide alguien de
 * Ronda con los datos ya decididos; aqui lo pide un desconocido desde un
 * formulario publico, y hay que comprobar que el subdominio que eligio se puede
 * dar.
 *
 * Se comprueba aqui y no solo en la validacion del formulario porque el alta
 * tambien llegara por la API y por el back-office, y la regla de que `www` no
 * es de nadie no puede vivir en una pantalla.
 */
final readonly class RegisterTenant
{
    public function __construct(
        private CreateTenant $createTenant,
    ) {}

    /**
     * @throws SubdomainUnavailable
     */
    public function __invoke(CreateTenantData $data): Tenant
    {
        $subdominio = mb_strtolower(trim($data->slug));

        if (ReservedSubdomains::taken($subdominio)) {
            throw SubdomainUnavailable::reserved($subdominio);
        }

        if (Tenant::query()->where('slug', $subdominio)->exists()) {
            throw SubdomainUnavailable::alreadyTaken($subdominio);
        }

        // Y el dominio completo: dos clientes con slugs distintos no pueden
        // acabar en la misma direccion.
        if (Domain::query()->where('domain', $data->domain)->exists()) {
            throw SubdomainUnavailable::alreadyTaken($subdominio);
        }

        return ($this->createTenant)($data);
    }
}
