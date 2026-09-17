<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Database\Seeders;

use Illuminate\Database\Seeder;
use Ronda\Scheduling\Application\Actions\EnsureHolidays;

/**
 * Calendario de feriados con el que arranca un cliente. RONDA-PLAN-MAESTRO.md
 * sec. 7.2 («seed: ... calendario de feriados PE»).
 *
 * Siembra el ano en curso y el siguiente. Los posteriores los completa la
 * materializacion diaria cuando los necesita (EnsureHolidays).
 */
final class HolidaysSeeder extends Seeder
{
    public function run(EnsureHolidays $ensureHolidays): void
    {
        $year = (int) now()->format('Y');

        $ensureHolidays($year);
        $ensureHolidays($year + 1);
    }
}
