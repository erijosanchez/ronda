<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programacion, feriados y obligaciones. RONDA-PLAN-MAESTRO.md sec. 8.3, 9.3
 * y ADR 0008.
 *
 * Dos desviaciones conscientes respecto a la tabla del plan:
 *
 * - `schedules` NO lleva zona horaria. La ventana («de 08:00 a 18:00») se
 *   interpreta en la zona de CADA sede, como manda la sec. 8.6. Una zona en la
 *   programacion obligaria a elegir cual de las dos manda cuando no coinciden.
 *
 * - `obligations` guarda tres instantes (apertura, vencimiento y cierre) en vez
 *   de un solo `due_at`, y la fecha local de la ocurrencia. Son una foto: si la
 *   programacion cambia manana, las obligaciones ya materializadas no se mueven.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->string('name');

            // nacional | regional
            $table->string('scope')->default('national');
            // Solo para los regionales (p. ej. «Cusco»).
            $table->string('region')->nullable();

            // De donde salio: la semilla calculada, una carga manual del
            // cliente o una sincronizacion oficial. Permite reemplazar la
            // semilla por la fuente oficial sin tocar lo que puso el cliente.
            $table->string('source')->default('seed');

            $table->timestamps();

            $table->unique(['date', 'scope', 'region']);
            $table->index('date');
        });

        Schema::create('schedules', function (Blueprint $table): void {
            $table->id();

            // RESTRICT: una plantilla con programaciones no se borra por debajo.
            $table->foreignId('template_id')->constrained()->restrictOnDelete();
            $table->string('name');

            // all_sites | zone | sites
            $table->string('scope');
            $table->foreignId('zone_id')->nullable()->constrained('zones')->restrictOnDelete();

            // Regla RFC 5545 sin DTSTART: el inicio lo da `starts_on`.
            $table->string('rrule', 500);

            // Ventana en hora LOCAL de cada sede.
            $table->time('window_start');
            $table->time('window_end');

            // Minutos de gracia tras el vencimiento: dentro, la entrega es
            // tardia; fuera, la obligacion se da por incumplida.
            $table->unsignedInteger('tolerance_minutes')->default(0);

            $table->boolean('skip_holidays')->default(true);
            $table->boolean('active')->default(true)->index();

            $table->date('starts_on');
            $table->date('ends_on')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('schedule_site', function (Blueprint $table): void {
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->primary(['schedule_id', 'site_id']);
        });

        Schema::create('obligations', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('schedule_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            // Copiada de la programacion: la bandeja filtra por plantilla sin
            // pasar por `schedules`.
            $table->foreignId('template_id')->constrained()->restrictOnDelete();

            // Dia de la ocurrencia en el calendario de la sede.
            $table->date('occurrence_date');

            // En UTC (regla de datos de la sec. 8.6).
            $table->timestamp('opens_at');
            $table->timestamp('due_at');
            // due_at + tolerancia. Pasado este instante, `missed`.
            $table->timestamp('closes_at');

            $table->string('status')->default('pending');

            // Se enlaza cuando exista el modulo Submissions.
            $table->unsignedBigInteger('submission_id')->nullable();
            $table->timestamp('fulfilled_at')->nullable();

            // Para `excused`: por que y quien lo decidio (sec. 9.3).
            $table->string('excuse_reason')->nullable();
            $table->foreignId('excused_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // La idempotencia de la materializacion descansa en esta
            // restriccion: el job diario puede reejecutarse sin duplicar.
            $table->unique(['schedule_id', 'site_id', 'occurrence_date']);

            // El motor de vencimientos (sec. 8.5).
            $table->index(['due_at', 'status']);
            $table->index(['closes_at', 'status']);
            // La lista de pendientes de hoy de una sede.
            $table->index(['site_id', 'occurrence_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obligations');
        Schema::dropIfExists('schedule_site');
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('holidays');
    }
};
