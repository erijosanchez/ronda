<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suscripciones. RONDA-PLAN-MAESTRO.md sec. 8.2 y 15.1
 *
 * Una por cliente y viva a la vez. El precio y el minimo de sedes se GUARDAN
 * aqui, copiados del plan al contratar: si manana sube la tarifa, quien firmo
 * antes sigue pagando la suya hasta que se le cambie a proposito. Leer el
 * precio del plan en cada cobro subiria el precio a todo el mundo a la vez, en
 * silencio.
 *
 * `external_id` es como se llama esta suscripcion en la pasarela. Hoy queda
 * nulo —el cobro lo dispara Ronda, no un objeto de suscripcion remoto— y esta
 * para las pasarelas que si lo necesiten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();

            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();

            // trialing | active | past_due | canceled
            $table->string('status')->index();
            // monthly | yearly
            $table->string('cycle');

            // Copia del plan al contratar, no una lectura viva.
            $table->decimal('price_per_site', 14, 2);
            $table->char('currency', 3);
            $table->unsignedSmallInteger('min_sites');

            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            // Cuando toca el proximo cobro. El comando de renovacion busca por
            // aqui, de ahi el indice.
            $table->timestamp('current_period_end')->nullable()->index();
            $table->timestamp('canceled_at')->nullable();

            // Con que pasarela se cobra y como se llama la tarjeta guardada.
            $table->string('gateway')->default('manual');
            $table->string('card_reference')->nullable();
            $table->string('external_id')->nullable();

            $table->timestamps();

            // Una fila por cliente, que se reusa si vuelve despues de
            // cancelar: el historial de lo que pago esta en `invoices`, que es
            // donde hay que mirarlo, y asi no hay que decidir cual de tres
            // suscripciones es «la buena».
            $table->unique('tenant_id', 'subscriptions_tenant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
