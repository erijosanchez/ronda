<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cobros emitidos. RONDA-PLAN-MAESTRO.md sec. 8.2 y 15.1
 *
 * Cada fila es lo que se le cobra a un cliente por un PERIODO concreto, con el
 * detalle de como salio el numero: cuantas sedes activas habia y a que precio.
 * Sin ese detalle, discutir una factura obliga a reconstruir el pasado.
 *
 * `(subscription_id, period_start)` es unico: es lo que impide que un comando
 * de renovacion ejecutado dos veces —o dos veces a la vez— cobre dos veces el
 * mismo mes. La idempotencia vive en la base, no en la confianza.
 *
 * Esto NO es el comprobante de SUNAT. La boleta o factura electronica la emite
 * un PSE (sec. 15.2) y se enlaza aqui con `document_url` cuando exista.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();

            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();

            // pending | paid | failed | void
            $table->string('status')->index();

            $table->decimal('amount', 14, 2);
            $table->char('currency', 3);

            // De donde sale el importe.
            $table->unsignedSmallInteger('billed_sites');
            $table->decimal('price_per_site', 14, 2);
            $table->unsignedSmallInteger('billed_months');

            $table->date('period_start');
            $table->date('period_end');

            $table->timestamp('issued_at');
            $table->timestamp('paid_at')->nullable();
            // Cuando toca reintentar un cobro rechazado. Nulo si no hay nada
            // que reintentar; indexado porque el comando busca por aqui.
            $table->timestamp('retry_after')->nullable()->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('failure_reason')->nullable();

            $table->string('gateway');
            // Como se llama este cobro en la pasarela, para conciliar.
            $table->string('external_id')->nullable();

            // El comprobante electronico del PSE, cuando lo haya.
            $table->string('document_url')->nullable();

            $table->timestamps();

            $table->unique(['subscription_id', 'period_start'], 'invoices_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
