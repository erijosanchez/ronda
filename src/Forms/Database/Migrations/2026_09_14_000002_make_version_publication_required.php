<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Toda version de plantilla nace publicada. RONDA-PLAN-MAESTRO.md sec. 9.2
 *
 * La migracion anterior dejo `published_at` nullable, preparando un concepto de
 * borrador que el plan NO contempla: alli el diseñador visual guarda y eso
 * publica una version nueva, sin estado intermedio. El estado intermedio vive
 * en el componente mientras se edita, no en la base.
 *
 * Se pone NOT NULL para que la decision quede en el esquema y no solo en un
 * comentario: una version sin fecha de publicacion deja de ser representable.
 *
 * `published_by` sigue nullable: la persona que publico puede darse de baja
 * despues, y la version tiene que sobrevivirla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_versions', function (Blueprint $table): void {
            $table->timestamp('published_at')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('template_versions', function (Blueprint $table): void {
            $table->timestamp('published_at')->nullable()->change();
        });
    }
};
