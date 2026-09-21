<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Ronda\Platform\Domain\Models\PlatformUser;

/**
 * La primera cuenta del equipo de Ronda. RONDA-PLAN-MAESTRO.md sec. 15.4
 *
 * Solo en desarrollo: en produccion las cuentas del equipo se crean a mano y
 * de una en una, porque cada una puede entrar a la operacion de cualquier
 * cliente. Una semilla con contrasena conocida en produccion seria una puerta
 * trasera escrita en el repositorio.
 *
 * Nace SIN segundo factor a proposito: al entrar por primera vez, la pantalla
 * obliga a configurarlo antes de dejar pasar.
 */
final class PlatformUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('PlatformUserSeeder no corre en produccion. Crea la cuenta a mano.');

            return;
        }

        $cuenta = PlatformUser::query()->updateOrCreate(
            ['email' => 'soporte@ronda.pe'],
            [
                'name' => 'Soporte Ronda',
                'password' => 'password-de-desarrollo',
                'is_active' => true,
            ],
        );

        $this->command?->info("Back-office: {$cuenta->email} / password-de-desarrollo (configura el 2FA al entrar)");
    }
}
