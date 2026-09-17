<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avisos y escalamiento. RONDA-PLAN-MAESTRO.md sec. 8.3, 9.3 y 9.4.
 *
 * `notifications` es la tabla estandar de Laravel, en la base del tenant: los
 * avisos de un cliente no se mezclan con los de otro ni por accidente.
 *
 * `sla_events` es la memoria del escalamiento: que aviso se mando ya sobre que
 * cosa y en que nivel. Es lo que hace que el job horario pueda repasar todo el
 * parque cada hora sin volver a avisar de lo mismo. La restriccion unica es la
 * garantia; el codigo solo intenta insertar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // La campana: lo no leido de una persona, lo mas reciente primero.
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });

        Schema::create('sla_events', function (Blueprint $table): void {
            $table->id();

            // A que se refiere: una obligacion o un envio. Polimorfico y no dos
            // columnas nulables, porque los avisos de ambos comparten escalera.
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');

            // obligation_due_soon | obligation_missed | review_overdue | ...
            $table->string('topic');
            // Peldano de la escalera: 0 el primer aviso, 1 el siguiente.
            $table->unsignedInteger('level')->default(0);

            // A cuanta gente se le mando. 0 significa «no habia a quien».
            $table->unsignedInteger('recipients')->default(0);
            $table->timestamp('occurred_at');

            $table->timestamps();

            // Un aviso por cosa, tema y nivel. Sin esto, el job horario
            // repetiria el mismo recordatorio cada hora hasta el vencimiento.
            $table->unique(['subject_type', 'subject_id', 'topic', 'level']);
            $table->index(['topic', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_events');
        Schema::dropIfExists('notifications');
    }
};
