<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Ronda\Platform\Domain\Models\Plan;
use Ronda\Platform\Domain\PlanCode;
use Ronda\Platform\Domain\PlanFeature;

/**
 * El catalogo de planes. RONDA-PLAN-MAESTRO.md sec. 3.6
 *
 * NO es semilla de desarrollo: sin planes cargados no hay limites que aplicar,
 * asi que corre tambien en produccion. Por eso es idempotente: vuelve a dejar
 * cada plan como dice esta tabla, y se puede ejecutar despues de cada
 * despliegue sin duplicar nada.
 *
 * Los precios son la hipotesis del §3.6 y el plan mismo avisa de que se
 * validan con entrevistas antes de escribir el checkout. Cambiarlos es un
 * UPDATE en esta tabla, no un cambio de codigo.
 */
final class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $planes = [
            [
                'code' => PlanCode::Starter->value,
                'name' => 'Starter',
                'price_per_site' => '29.00',
                'currency' => 'PEN',
                // Se cobran 5 sedes aunque tenga 2: es el suelo del plan.
                'min_sites' => 5,
                'max_templates' => 3,
                'storage_gb_per_site' => 1,
                'features' => [],
                'is_public' => true,
                'position' => 1,
            ],
            [
                'code' => PlanCode::Pro->value,
                'name' => 'Pro',
                'price_per_site' => '49.00',
                'currency' => 'PEN',
                'min_sites' => 1,
                'max_templates' => null,
                'storage_gb_per_site' => 5,
                // El SSO es opcional en el §3.6, no viene incluido: se
                // enciende por cliente desde el back-office.
                'features' => [
                    PlanFeature::WhatsApp->value,
                    PlanFeature::Api->value,
                    PlanFeature::CustomWorkflows->value,
                ],
                'is_public' => true,
                'position' => 2,
            ],
            [
                'code' => PlanCode::Enterprise->value,
                'name' => 'Enterprise',
                // Cotizado: el precio se acuerda uno a uno y se guarda en la
                // suscripcion, no en el plan. Por eso no se muestra ni se
                // puede elegir al registrarse.
                'price_per_site' => '0.00',
                'currency' => 'PEN',
                'min_sites' => 1,
                'max_templates' => null,
                'storage_gb_per_site' => null,
                'features' => array_map(
                    static fn (PlanFeature $feature): string => $feature->value,
                    PlanFeature::cases(),
                ),
                'is_public' => false,
                'position' => 3,
            ],
        ];

        foreach ($planes as $plan) {
            Plan::query()->updateOrCreate(['code' => $plan['code']], $plan);
        }

        $this->command?->info('Planes cargados: '.count($planes).'.');
    }
}
