<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exportaciones. RONDA-PLAN-MAESTRO.md sec. 13
 *
 * «Exportacion de 50 000 filas: en cola, con notificacion al terminar; nunca en
 * la peticion.» Esta tabla es el encargo: quien lo pidio, con que filtros, en
 * que estado y donde quedo el archivo.
 *
 * El archivo vive en el mismo bucket privado que la evidencia, bajo el prefijo
 * del tenant. Se descarga por la aplicacion, nunca por una URL del bucket.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exports', function (Blueprint $table): void {
            $table->id();

            // submissions | ... (mas tipos cuando hagan falta)
            $table->string('type');
            // queued | processing | completed | failed
            $table->string('status')->default('queued');

            // Los filtros tal como se pidieron, incluidas las sedes que veia
            // quien lo pidio: el job corre sin sesion, y congelarlas evita que
            // un cambio de permisos ensanche una exportacion ya encargada.
            $table->jsonb('filters');

            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();

            $table->string('disk')->nullable();
            $table->string('path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedInteger('rows')->nullable();
            $table->unsignedBigInteger('bytes')->nullable();

            // Por que fallo, para poder decirselo a quien lo pidio.
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            // «Mis exportaciones», lo mas reciente primero.
            $table->index(['requested_by', 'id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
