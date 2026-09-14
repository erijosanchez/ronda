<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Completa la tabla de usuarios del tenant. RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * La migracion original se quedo corta: el plan pide tambien telefono, estado
 * y borrado logico. Se anaden aparte y no editando aquella porque el parque ya
 * tiene bases creadas, y una migracion aplicada no se vuelve a ejecutar.
 *
 * Solo anade columnas, asi que es compatible hacia atras (sec. 7.3): el codigo
 * anterior sigue funcionando mientras se despliega.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Telefono personal: dato sensible, va cifrado (sec. 8.6). Por eso
            // es `text` y no `string`: el texto cifrado es mas largo que el
            // original y no cabe en 255 de forma fiable.
            //
            // El precio es que no se puede buscar por telefono en SQL ni poner
            // un indice. Se acepta a proposito.
            $table->text('phone')->nullable()->after('email');

            // Una persona que se va se suspende; borrarla pierde su historial
            // de envios y revisiones.
            $table->string('status')->default('active')->index()->after('phone');

            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropColumn(['phone', 'status']);
        });
    }
};
