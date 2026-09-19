<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuando quedo listo un cliente. RONDA-PLAN-MAESTRO.md sec. 15.3
 *
 * La provision corre en cola (crear base, migrar, sembrar y crear al
 * propietario), asi que entre registrarse y poder entrar pasan unos segundos.
 * Con esta marca, la pantalla de «preparando tu cuenta» sabe cuando mandar a
 * la persona a su nuevo dominio en vez de adivinarlo.
 *
 * Es central y no del tenant a proposito: hay que poder preguntarlo sin
 * conectarse a una base que quiza no existe todavia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->timestamp('provisioned_at')->nullable()->after('trial_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('provisioned_at');
        });
    }
};
