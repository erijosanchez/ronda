<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suplantacion: el vale de un solo uso y el registro de lo que se hizo.
 * RONDA-PLAN-MAESTRO.md sec. 8.2 y 15.4
 *
 * Dos tablas y dos vidas distintas:
 *
 *   - `tenant_user_impersonation_tokens` es del paquete de tenancy: un vale que
 *     vive segundos y se borra al usarse. Se declara aqui porque el paquete lo
 *     entrega como asset y no lo publica solo.
 *   - `impersonation_log` es NUESTRO y no se borra nunca. Es la respuesta a «en
 *     mi cuenta entro alguien de ustedes, ¿quien, cuando y por que?». Sin el,
 *     la suplantacion es una puerta trasera con buenas intenciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_user_impersonation_tokens', function (Blueprint $table): void {
            $table->string('token', 128)->primary();
            $table->string('tenant_id');
            $table->string('user_id');
            $table->string('auth_guard');
            $table->string('redirect_url');
            $table->timestamp('created_at');

            $table->foreign('tenant_id')->references('id')->on('tenants')
                ->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::create('impersonation_log', function (Blueprint $table): void {
            $table->id();

            // Quien entro. `restrictOnDelete`: una cuenta del equipo no se
            // borra dejando huerfano lo que hizo.
            $table->foreignId('platform_user_id')->constrained('platform_users')->restrictOnDelete();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();

            // A quien suplanto. Se guarda tambien el correo, copiado: el
            // usuario vive en la base del cliente y puede borrarse, y el
            // registro tiene que seguir diciendo a quien se suplanto.
            $table->unsignedBigInteger('impersonated_user_id');
            $table->string('impersonated_user_email');

            // El motivo es obligatorio (sec. 15.4). No hay valor por defecto a
            // proposito: entrar sin decir por que no es una opcion.
            $table->text('reason');

            $table->timestamp('started_at');
            // Cuando caduca la sesion suplantada. El limite de tiempo se
            // guarda aqui y no solo en la sesion: la sesion la controla el
            // navegador, y esto es lo que se audita.
            $table->timestamp('expires_at');
            $table->timestamp('ended_at')->nullable();

            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();

            $table->timestamps();

            // El back-office lista por cliente y por fecha.
            $table->index(['tenant_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonation_log');
        Schema::dropIfExists('tenant_user_impersonation_tokens');
    }
};
