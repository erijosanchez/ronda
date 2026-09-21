<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personal de Ronda. RONDA-PLAN-MAESTRO.md sec. 8.2 y 15.4
 *
 * SEPARADO de los usuarios de cada cliente, y a proposito: quien da soporte no
 * es usuario de nadie. Si compartieran tabla, un fallo de aislamiento dejaria
 * de ser «un cliente ve datos de otro» para pasar a ser «un cliente entra al
 * back-office».
 *
 * Vive en la base central, que es la unica que conoce a todos los clientes.
 *
 * El 2FA no es opcional (sec. 15.4): esta cuenta puede entrar a la operacion de
 * cualquier cliente, asi que una contrasena sola no basta. Mientras
 * `two_factor_confirmed_at` sea nulo, la sesion no pasa de la pantalla de
 * configurarlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_users', function (Blueprint $table): void {
            $table->id();

            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');

            // TOTP. El secreto y los codigos de recuperacion van cifrados por
            // el cast del modelo, no en claro.
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            // Dar de baja a alguien del equipo no borra lo que hizo: el
            // registro de suplantaciones apunta aqui y tiene que seguir
            // pudiendo decir quien entro.
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_users');
    }
};
