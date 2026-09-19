<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planes del SaaS. RONDA-PLAN-MAESTRO.md sec. 8.2 y 3.6
 *
 * Central y no del tenant: el plan es un acuerdo entre Ronda y el cliente, no
 * un dato del cliente. Si viviera en su base, el cliente podria cambiarselo.
 *
 * El precio es por SEDE ACTIVA al mes (sec. 15.1): contar sedes y no usuarios
 * evita que el cliente racione accesos, que es lo que mata la adopcion.
 *
 * Los limites nulos significan «sin limite», no «cero»: es la diferencia entre
 * el plan Pro (plantillas ilimitadas) y un plan que no deja crear ninguna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();

            // `starter`, `pro`, `enterprise`. Es la clave con la que el codigo
            // se refiere a un plan; el nombre es solo para mostrar.
            $table->string('code')->unique();
            $table->string('name');

            // Dinero en numeric con la moneda aparte, nunca float (CLAUDE.md).
            $table->decimal('price_per_site', 14, 2);
            $table->char('currency', 3);

            // Minimo facturable: Starter cobra 5 sedes aunque tenga 2.
            $table->unsignedSmallInteger('min_sites')->default(1);

            // Nulo = ilimitado.
            $table->unsignedSmallInteger('max_templates')->nullable();
            $table->unsignedSmallInteger('storage_gb_per_site')->nullable();

            // Banderas del plan (`whatsapp`, `api`, `custom_workflows`, `sso`).
            // JSONB y no columnas: cada plan nuevo traeria una migracion.
            $table->jsonb('features')->default('[]');

            // Los planes cotizados no se muestran en la pagina de precios ni
            // se pueden elegir al registrarse.
            $table->boolean('is_public')->default(true);
            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
