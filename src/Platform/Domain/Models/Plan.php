<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Ronda\Platform\Domain\PlanCode;
use Ronda\Platform\Domain\PlanFeature;
use Ronda\Platform\Domain\ValueObjects\PlanLimits;
use Stancl\Tenancy\Database\Concerns\CentralConnection;
use UnexpectedValueException;

/**
 * Un plan del SaaS, tal como esta cargado en la base central.
 * RONDA-PLAN-MAESTRO.md sec. 3.6 y 8.2
 *
 * Los precios y los limites son datos, no codigo: cambiar «Starter llega a 3
 * plantillas» es un UPDATE, no un despliegue. Lo que si esta en codigo es que
 * el plan de entrada es Starter (PlanCode::default).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $price_per_site
 * @property string $currency
 * @property int $min_sites
 * @property int|null $max_templates
 * @property int|null $storage_gb_per_site
 * @property list<string> $features
 * @property bool $is_public
 */
final class Plan extends Model
{
    // Sin esto, preguntar por el plan DENTRO de un cliente buscaria la tabla
    // `plans` en su base, donde no existe: los planes son de Ronda, no del
    // cliente, y viven en la central pase lo que pase con la conexion en curso.
    use CentralConnection;

    protected $guarded = [];

    public function code(): ?PlanCode
    {
        return PlanCode::tryFrom($this->code);
    }

    public function limits(): PlanLimits
    {
        return new PlanLimits(
            maxTemplates: $this->max_templates,
            storageGbPerSite: $this->storage_gb_per_site,
        );
    }

    public function includes(PlanFeature $feature): bool
    {
        return in_array($feature->value, $this->features, true);
    }

    /**
     * Lo que se factura al mes: el precio por sede, por las sedes activas, con
     * el minimo del plan como suelo (sec. 15.1).
     *
     * Devuelve una cadena y no un float: el dinero no se representa en coma
     * flotante en ningun punto del sistema (CLAUDE.md).
     */
    public function monthlyPriceFor(int $activeSites): string
    {
        $facturables = max($activeSites, $this->min_sites);

        // El precio sale de una columna numeric, asi que siempre es numerico;
        // el guardia esta para que no lo deje de ser sin que nadie se entere.
        $precio = $this->price_per_site;

        if (! is_numeric($precio)) {
            throw new UnexpectedValueException("El plan «{$this->code}» tiene un precio que no es un numero.");
        }

        return bcmul($precio, (string) $facturables, 2);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_public' => 'boolean',
        ];
    }
}
