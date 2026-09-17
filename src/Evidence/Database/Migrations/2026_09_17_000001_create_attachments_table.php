<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evidencia de un envio. RONDA-PLAN-MAESTRO.md sec. 8.3, 9.5 y ADR 0009.
 *
 * El archivo vive en el bucket privado; aqui queda lo que lo hace probatorio:
 * su SHA-256, donde y cuando se tomo, y a cuantos metros de la sede.
 *
 * Una desviacion consciente respecto al plan: la firma NO tiene tabla propia
 * (`signatures`). Es una imagen con hash, autor, IP y marca de tiempo, igual
 * que cualquier otra evidencia; una segunda tabla duplicaria todo eso y
 * obligaria a la revision a mirar dos sitios. Se distingue por `kind`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table): void {
            $table->id();

            // RESTRICT: la evidencia de un envio no desaparece por debajo.
            $table->foreignId('submission_id')->constrained()->restrictOnDelete();
            // El campo del formulario al que responde.
            $table->string('field_key');

            // photo | file | signature
            $table->string('kind');

            $table->string('disk');
            // Unica: dos filas apuntando al mismo objeto harian que borrar una
            // rompiera la otra.
            $table->string('path')->unique();
            $table->string('original_name');
            // Detectado por contenido, no el que declaro el navegador.
            $table->string('mime_type');
            $table->unsignedBigInteger('bytes');
            // Del archivo TAL COMO SE GUARDO (ya saneado): es lo verificable.
            $table->char('sha256', 64)->index();

            // Donde. `location_source` dice de donde salio: `exif` (la camara)
            // o `device` (el navegador al entregar). Nula si no hubo ninguna.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('location_source')->nullable();
            // Metros hasta la sede, calculados al guardar. Nula si falta
            // alguna de las dos posiciones.
            $table->unsignedInteger('distance_meters')->nullable();

            // Cuando se tomo, segun el EXIF, en UTC. Se contrasta con
            // `submissions.submitted_at` para detectar fotos de otro dia.
            $table->timestamp('captured_at')->nullable();

            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->ipAddress('ip_address')->nullable();

            $table->timestamps();

            $table->index(['submission_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
