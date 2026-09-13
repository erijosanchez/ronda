<?php

declare(strict_types=1);

namespace Ronda\Directory\Database\Seeders;

use Illuminate\Database\Seeder;
use Ronda\Directory\Domain\Models\Position;

/**
 * Catalogo de cargos con el que arranca un cliente.
 * RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * Son un punto de partida, no una lista cerrada: el cliente anade los suyos.
 * El nivel ordena el organigrama y alimenta las reglas de aprobacion por nivel
 * (sec. 9.4); no concede permisos, que eso son los roles.
 *
 * Idempotente: se puede reejecutar sobre un tenant existente sin duplicar ni
 * pisar lo que el cliente haya cambiado.
 */
final class PositionsSeeder extends Seeder
{
    /**
     * Cargos base: nombre => nivel.
     *
     * @var array<string, int>
     */
    private const array POSITIONS = [
        'Gerente de operaciones' => 40,
        'Jefe de zona' => 30,
        'Supervisor' => 20,
        'Encargado de sede' => 10,
        'Personal de sede' => 0,
    ];

    public function run(): void
    {
        foreach (self::POSITIONS as $name => $level) {
            Position::query()->firstOrCreate(['name' => $name], ['level' => $level]);
        }
    }
}
