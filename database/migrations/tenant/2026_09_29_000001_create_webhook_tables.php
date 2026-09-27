<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Webhooks salientes. RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * En la base de cada cliente: a donde manda sus avisos una empresa es asunto
 * suyo, y el registro de entregas tambien.
 *
 * Dos tablas con dos vidas:
 *
 *   - `webhook_endpoints`: pocas filas, largas de vida. Guardan el secreto con
 *     el que se firma cada envio.
 *   - `webhook_deliveries`: una fila por intento de entrega. Es el registro
 *     consultable que pide el plan, y lo que permite reenviar a mano lo que
 *     fallo sin tener que provocar el evento otra vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table): void {
            $table->id();

            $table->string('url');
            $table->string('description')->nullable();

            // Con el que se firma el cuerpo (HMAC-SHA256). Cifrado por el cast
            // del modelo: quien lea la base no puede falsificar envios.
            $table->text('secret');

            // A que eventos esta suscrito. JSONB y no tabla aparte: son pocos
            // y se leen siempre enteros.
            //
            // `subscribed_events` y no `events`: en un modelo de Eloquent,
            // `events` fue el nombre antiguo de `dispatchesEvents`, y Rector
            // renombra la propiedad por su cuenta cuando la ve.
            $table->jsonb('subscribed_events')->default('[]');

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            // Tras demasiados fallos seguidos se apaga solo: seguir golpeando
            // una URL muerta cada minuto es maltratar a un servidor ajeno.
            $table->unsignedSmallInteger('consecutive_failures')->default(0);

            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();

            $table->string('event')->index();
            $table->jsonb('payload');

            // pending | delivered | failed
            $table->string('status')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('error')->nullable();

            $table->timestamp('delivered_at')->nullable();
            // Cuando toca el siguiente intento. Indexado porque el comando de
            // reintentos busca por aqui.
            $table->timestamp('next_attempt_at')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
    }
};
