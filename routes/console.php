<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
| Las ejecuta el contenedor `scheduler` (`php artisan schedule:work`).
*/

// El motor de obligaciones (ADR 0008): cierra lo vencido y materializa los
// proximos dias en cada tenant.
//
// Cada hora y no una vez al dia. El cierre de una obligacion cae a cualquier
// hora (su ventana mas la tolerancia), y marcarla como incumplida con horas de
// retraso retrasa tambien el escalamiento. La materializacion es idempotente,
// asi que repetirla cada hora no duplica nada.
Schedule::command('obligations:materialize')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
