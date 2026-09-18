<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KPI materializados. RONDA-PLAN-MAESTRO.md sec. 8.3, 9.6 y 13.
 *
 * «Ninguna pantalla agrega sobre la tabla de envios en tiempo real» (sec. 13):
 * el tablero lee de aqui, y aqui escribe un job.
 *
 * Grano: dia de la sede, sede y plantilla. El dia es `occurrence_date` de la
 * obligacion, que ya viene en el calendario local de la sede (ADR 0008); asi
 * una sede de Iquitos y otra de Lima no se mezclan por la zona horaria.
 *
 * Desviaciones conscientes respecto a la tabla del plan:
 *
 * - No hay `kpi_weekly` ni `kpi_monthly`. Una semana y un mes son sumas de
 *   estas filas sobre un rango de fechas, con indice; duplicarlas seria
 *   duplicar tambien la invalidacion (una correccion de ayer cambia el mes).
 *   Se anadiran cuando el volumen lo pida, no antes.
 * - El tiempo de revision se guarda como SUMA y CUENTA, no como mediana. La
 *   mediana del plan no se puede sumar entre filas: la de una semana no sale de
 *   las de sus dias. Con suma y cuenta, el promedio de cualquier periodo es
 *   exacto. La mediana vuelve cuando haya una pantalla que la pida sobre datos
 *   crudos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_daily', function (Blueprint $table): void {
            $table->id();

            $table->date('kpi_date');
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();

            // Cumplimiento: fulfilled / (fulfilled + missed). `excused` no
            // ensucia el KPI (sec. 9.1): se cuenta aparte.
            $table->unsignedInteger('fulfilled')->default(0);
            $table->unsignedInteger('missed')->default(0);
            $table->unsignedInteger('excused')->default(0);

            // Puntualidad.
            $table->unsignedInteger('on_time')->default(0);
            $table->unsignedInteger('late')->default(0);
            $table->unsignedBigInteger('minutes_late_sum')->default(0);

            // Calidad: aprobado a la primera, sin rechazo por medio.
            $table->unsignedInteger('approved')->default(0);
            $table->unsignedInteger('rejected')->default(0);
            $table->unsignedInteger('approved_first_try')->default(0);

            // Tiempo de revision, en minutos.
            $table->unsignedInteger('reviews_resolved')->default(0);
            $table->unsignedBigInteger('review_minutes_sum')->default(0);

            $table->timestamps();

            // El job recalcula por (dia, sede, plantilla): esta restriccion es
            // lo que hace que repetirlo no duplique.
            $table->unique(['kpi_date', 'site_id', 'template_id']);
            // El tablero: un rango de fechas, opcionalmente por sede.
            $table->index(['kpi_date', 'site_id']);
            $table->index(['template_id', 'kpi_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_daily');
    }
};
