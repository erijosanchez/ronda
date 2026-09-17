<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los envios: lo que llego. RONDA-PLAN-MAESTRO.md sec. 8.3 y ADR 0012.
 *
 * `submissions.data` guarda la respuesta completa y fiel: es la fuente de
 * verdad. `submission_values` replica SOLO los campos reportables en columnas
 * tipadas, para filtros y KPI sin tocar el JSONB. Las dos cosas se escriben en
 * la misma transaccion; si divergen, gana `data`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('template_id')->constrained()->restrictOnDelete();
            // La version CON LA QUE se respondio. Es lo que permite leer un envio
            // de marzo con la plantilla de marzo (ADR 0012).
            $table->foreignId('template_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();

            // Una obligacion se cumple con un solo envio. La unicidad la
            // sostiene la base y no solo la Action: dos pestanas enviando a la
            // vez no pueden cumplir la misma obligacion dos veces.
            $table->foreignId('obligation_id')->nullable()->unique()->constrained()->restrictOnDelete();

            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();

            $table->string('state')->index();

            $table->jsonb('data');

            $table->timestamp('submitted_at');
            $table->boolean('is_late')->default(false);
            $table->unsignedInteger('minutes_late')->default(0);

            $table->timestamps();

            // El 80 % de las consultas de pantalla (sec. 8.5).
            $table->index(['site_id', 'template_id', 'submitted_at']);
        });

        // Busquedas dentro de las respuestas (sec. 8.5).
        DB::statement('CREATE INDEX submissions_data_gin ON submissions USING GIN (data)');

        Schema::create('submission_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('field_key');

            // Una sola columna con valor por fila, segun el tipo del campo.
            $table->text('value_text')->nullable();
            // Decimal exacto y nunca float (CLAUDE.md). Precision (18,4) y no
            // (14,2): aqui caen importes pero tambien numeros como puntajes, y
            // (14,2) redondearia un 4,375. Un importe se guarda igual de exacto.
            $table->decimal('value_numeric', 18, 4)->nullable();
            $table->date('value_date')->nullable();
            $table->boolean('value_bool')->nullable();

            $table->unique(['submission_id', 'field_key']);
            // Lo que consultan los KPI: un campo concreto por valor.
            $table->index(['field_key', 'value_numeric']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_values');
        Schema::dropIfExists('submissions');
    }
};
