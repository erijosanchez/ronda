<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La huella del envio que salio de un telefono. RONDA-PLAN-MAESTRO.md sec. 13.3
 *
 * La cola de envio offline reintenta: si la respuesta se perdio por el camino
 * (el tunel, el ascensor, el ascensor con tunel), el telefono no sabe si su
 * entrega llego y vuelve a mandarla. Con este identificador, que genera el
 * dispositivo antes de enviar, el servidor reconoce el reintento y devuelve el
 * mismo envio en vez de crear otro.
 *
 * Es nulo para lo que se entrega desde el formulario normal: ahi la respuesta
 * viaja en la misma peticion y no hay nada que reconciliar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            // Unico: la garantia contra el duplicado es de la base, no del
            // codigo. Dos reintentos a la vez no pueden colarse entre la
            // comprobacion y la escritura.
            $table->string('client_token', 64)->nullable()->unique()->after('author_id');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->dropColumn('client_token');
        });
    }
};
