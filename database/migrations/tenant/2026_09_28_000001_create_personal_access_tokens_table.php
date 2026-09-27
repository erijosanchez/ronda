<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tokens de la API. RONDA-PLAN-MAESTRO.md sec. 13.1
 *
 * Va en la base de CADA CLIENTE y no en la central, aunque el paquete la
 * ponga por defecto en la principal: un token pertenece a una persona, y las
 * personas viven en la base de su cliente (ADR 0002). En la central no existe
 * siquiera la tabla `users` a la que apuntaria.
 *
 * `abilities` guarda los alcances del token (`submissions:read`,
 * `sites:write`...): el plan exige que cada endpoint declare el suyo y que un
 * token solo pueda lo que se le concedio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
