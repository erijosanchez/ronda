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

// El repaso de SLA (sec. 9.4): recordatorios previos, escalamiento de lo
// incumplido y de lo que lleva demasiado esperando revision.
//
// Diez minutos despues de la materializacion, y no a la vez: quien marca una
// obligacion como incumplida es el job anterior, y avisar antes de que lo haga
// dejaria el escalamiento una hora por detras. Cada aviso se anota antes de
// mandarse, asi que solaparse no duplicaria nada; el orden es por puntualidad,
// no por seguridad.
Schedule::command('notifications:sla')
    ->hourlyAt(10)
    ->withoutOverlapping()
    ->onOneServer();

// Los KPI (sec. 9.6): se materializan por job y ninguna pantalla agrega sobre
// la tabla de envios en tiempo real.
//
// Cada hora y sobre una ventana de dias, no solo sobre hoy: una aprobacion o
// una justificacion de ayer cambian cifras de dias ya cerrados. Recalcular el
// rango entero es idempotente.
Schedule::command('kpi:recalculate')
    ->hourlyAt(20)
    ->withoutOverlapping()
    ->onOneServer();
