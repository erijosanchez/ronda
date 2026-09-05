<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla central de clientes. RONDA-PLAN-MAESTRO.md sec. 8.2
 *
 * `data` es la columna JSONB que stancl/tenancy usa para todo atributo que no
 * tenga columna propia. Las que si la tienen se declaran en
 * Tenant::getCustomColumns().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            // ULID, no UUID: ordena por tiempo y no fragmenta el indice.
            $table->ulid('id')->primary();

            $table->string('name');
            $table->string('slug')->unique();

            // Estado del ciclo de vida (sec. 7.2). Indexado porque el
            // back-office lista por estado y el job de facturacion filtra por
            // el a diario.
            $table->string('status')->index();
            $table->timestamp('trial_ends_at')->nullable();

            $table->timestamps();
            $table->jsonb('data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
