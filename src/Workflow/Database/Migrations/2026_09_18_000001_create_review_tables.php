<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revision de envios. RONDA-PLAN-MAESTRO.md sec. 8.3, 8.5 y 9.4.
 *
 * Desviaciones conscientes respecto a la tabla del plan:
 *
 * - En lugar de `reviews` (solo decisiones) hay `submission_transitions`: cada
 *   cambio de estado con quien, cuando y por que, incluidos el envio, la toma y
 *   la correccion. Una decision es una transicion mas; tener el historial
 *   entero en un sitio es lo que permite reconstruir que paso con un arqueo.
 * - `workflows`, `workflow_states` y `workflow_transitions` (flujos
 *   configurables por plantilla) no se crean todavia: el flujo es el del plan,
 *   declarado en SubmissionState. Se haran cuando exista un disenador que los
 *   use; crearlas vacias seria prometer algo que el codigo no lee.
 * - `submission_revisions` no esta en el plan: al corregir un envio rechazado,
 *   lo que se rechazo no puede desaparecer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            // Quien lo tomo para revisar. Mientras este en revision, solo esa
            // persona decide.
            $table->foreignId('reviewer_id')->nullable()->after('author_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('review_started_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            // Cuantas veces se ha entregado: 1 el original, +1 por correccion.
            $table->unsignedInteger('revision')->default(1);
        });

        // La bandeja de revision (sec. 8.5): lo pendiente de tomar es una
        // fraccion pequena de todos los envios.
        DB::statement("CREATE INDEX submissions_inbox_idx ON submissions (submitted_at) WHERE state = 'submitted'");

        Schema::create('submission_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')->constrained()->restrictOnDelete();

            // Null en la primera: el envio nace en `submitted`.
            $table->string('from_state')->nullable();
            $table->string('to_state');

            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            // Obligatorio al rechazar (lo exige la Action, no la base: el
            // resto de transiciones no lo lleva).
            $table->text('comment')->nullable();

            // Solo se inserta: una transicion no se edita.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['submission_id', 'id']);
        });

        Schema::create('submission_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')->constrained()->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['submission_id', 'id']);
        });

        Schema::create('submission_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')->constrained()->restrictOnDelete();
            // El numero de revision que se sustituyo.
            $table->unsignedInteger('number');
            $table->foreignId('template_version_id')->constrained()->restrictOnDelete();
            // La respuesta tal como estaba, ids de evidencia incluidos: los
            // archivos antiguos siguen en `attachments`.
            $table->jsonb('data');
            // Por que se rechazo esa revision.
            $table->text('rejection_comment')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['submission_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_revisions');
        Schema::dropIfExists('submission_comments');
        Schema::dropIfExists('submission_transitions');

        DB::statement('DROP INDEX IF EXISTS submissions_inbox_idx');

        Schema::table('submissions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewer_id');
            $table->dropColumn(['review_started_at', 'reviewed_at', 'revision']);
        });
    }
};
