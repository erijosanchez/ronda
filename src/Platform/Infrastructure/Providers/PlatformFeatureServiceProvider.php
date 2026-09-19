<?php

declare(strict_types=1);

namespace Ronda\Platform\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;
use Ronda\Platform\Domain\Contracts\PlanProvider;
use Ronda\Platform\Domain\PlanFeature;

/**
 * Declara las banderas del producto y las ata al plan contratado.
 * RONDA-PLAN-MAESTRO.md sec. 3.6
 *
 * El ambito es el CLIENTE, no el usuario: un plan lo contrata la empresa, y
 * dentro de ella la respuesta es la misma para todos. Pennant, por defecto,
 * resuelve contra el usuario autenticado, que aqui seria la pregunta
 * equivocada.
 *
 * Vive en Infrastructure porque es donde se enchufan los paquetes; quien
 * pregunta por una bandera solo ve `Feature::active('api')` o el contrato
 * PlanProvider, y no sabe que existe pennant ni de que plan sale la respuesta.
 */
final class PlatformFeatureServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Feature::resolveScopeUsing(fn (?string $driver): mixed => tenant());

        foreach (PlanFeature::cases() as $feature) {
            Feature::define(
                $feature->value,
                fn (): bool => $this->app->make(PlanProvider::class)->allows($feature),
            );
        }
    }
}
