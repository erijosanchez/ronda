<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El plan que tiene contratado cada cliente.
 * RONDA-PLAN-MAESTRO.md sec. 8.2
 *
 * Nulo es un estado real y no un descuido: un cliente en prueba todavia no
 * eligio plan. Mientras tanto se le aplican los limites del plan de entrada,
 * que es lo que decide PlanProvider.
 *
 * `restrictOnDelete`: borrar un plan que alguien tiene contratado dejaria a ese
 * cliente sin limites que aplicar. Los planes se retiran marcandolos como no
 * publicos, no borrandolos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->foreignId('plan_id')->nullable()->after('status')
                ->constrained('plans')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('plan_id');
        });
    }
};
