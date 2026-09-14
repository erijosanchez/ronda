<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plantillas de formulario y sus versiones. Ver docs/adr/0012.
 *
 * `templates` guarda la identidad estable; `template_versions`, el esquema
 * completo de cada version como documento JSONB.
 *
 * Una version publicada NO se modifica nunca: editar crea un borrador nuevo. Es
 * lo que permite que un envio de marzo se siga leyendo con la plantilla de
 * marzo y no con la de hoy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table): void {
            $table->id();

            // Codigo estable del cliente. Sobrevive a los cambios de nombre y
            // es lo que referencia la programacion.
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();

            $table->string('status')->default('draft')->index();

            // Version vigente. Nullable porque una plantilla existe antes de
            // publicar nada. La clave foranea se anade despues: apunta a una
            // tabla que todavia no existe en este punto.
            $table->unsignedBigInteger('current_version_id')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('template_versions', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('template_id')->constrained()->cascadeOnDelete();

            // Correlativo por plantilla, empezando en 1. Es lo que ve el
            // usuario («version 3»), no el id.
            $table->unsignedInteger('number');

            // El esquema entero: campos, reglas, condiciones. Se lee siempre
            // completo, de ahi que sea un documento y no filas (ADR 0012).
            $table->jsonb('schema');

            // Sin fecha de publicacion es un borrador.
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['template_id', 'number']);
            // La bandeja busca borradores por plantilla.
            $table->index(['template_id', 'published_at']);
        });

        Schema::table('templates', function (Blueprint $table): void {
            // RESTRICT y no CASCADE: borrar la version vigente dejaria la
            // plantilla apuntando al vacio.
            $table->foreign('current_version_id')
                ->references('id')
                ->on('template_versions')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table): void {
            $table->dropForeign(['current_version_id']);
        });

        Schema::dropIfExists('template_versions');
        Schema::dropIfExists('templates');
    }
};
