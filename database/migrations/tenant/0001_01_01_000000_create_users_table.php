<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Usuarios del cliente. Vive en la base del TENANT, no en la central.
 * RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * Que esta tabla no exista en la base central es el nucleo del ADR 0002: un
 * scope olvidado no puede filtrar usuarios entre clientes porque en esa
 * conexion no hay usuarios de otro cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // 2FA de Fortify. Obligatorio para `owner`, `admin` y cualquier rol
            // con aprobacion financiera (sec. 10.2). Las columnas van aqui y no
            // en la migracion publicada del paquete para no depender de un
            // `vendor:publish` que se puede olvidar: sin ellas, activar la
            // feature de 2FA revienta en tiempo de ejecucion.
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
