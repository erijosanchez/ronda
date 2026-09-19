<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Http;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Platform\Application\Actions\ChargeInvoice;
use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Actions\IssueInvoice;
use Ronda\Platform\Application\Actions\StartSubscription;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Billing\BillingCycle;
use Ronda\Platform\Domain\Billing\ChargeRequest;
use Ronda\Platform\Domain\Billing\ChargeResult;
use Ronda\Platform\Domain\Billing\InvoiceStatus;
use Ronda\Platform\Domain\Billing\SubscriptionStatus;
use Ronda\Platform\Domain\Contracts\BillingGateway;
use Ronda\Platform\Domain\Exceptions\BillingNotConfigured;
use Ronda\Platform\Domain\Models\Invoice;
use Ronda\Platform\Domain\Models\Subscription;
use Ronda\Platform\Domain\Models\Tenant;
use Ronda\Platform\Domain\PlanCode;
use Ronda\Platform\Domain\States\Active;
use Ronda\Platform\Domain\States\PastDue;
use Ronda\Platform\Infrastructure\Billing\BillingGatewayFactory;
use Ronda\Platform\Infrastructure\Billing\CulqiGateway;
use Ronda\Platform\Infrastructure\Billing\ManualBillingGateway;

// Facturacion. RONDA-PLAN-MAESTRO.md sec. 15.1 y 15.2
//
// Lo que se prueba: que el importe sale de las sedes activas del cliente, que
// un periodo no se puede cobrar dos veces, que un rechazo reintenta antes de
// marcar moroso a nadie, y que cambiar de pasarela no cambia nada de lo
// anterior.

const CLAVE_COBROS = 'una-contrasena-larga-de-prueba';

beforeEach(function (): void {
    $this->seed(PlanSeeder::class);

    $this->tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Acme',
        slug: 'acme',
        domain: 'acme.ronda.test',
        ownerName: 'Duena de Acme',
        ownerEmail: 'owner@acme.test',
        ownerPassword: CLAVE_COBROS,
    ));
});

afterEach(function (): void {
    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    dropTenantDatabase($tenant);
});

function suscripcionDeAcme(): Subscription
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;

    return Subscription::query()->where('tenant_id', $tenant->id)->firstOrFail();
}

/**
 * Deja la suscripcion lista para cobrar: fuera de prueba, con tarjeta guardada
 * y el periodo ya vencido.
 */
function listaParaCobrar(CarbonImmutable $inicio): Subscription
{
    $suscripcion = suscripcionDeAcme();

    $suscripcion->forceFill([
        'status' => SubscriptionStatus::Active,
        'trial_ends_at' => $inicio->subDay(),
        'current_period_start' => $inicio,
        'current_period_end' => $inicio->addMonth(),
        'card_reference' => 'crd_test_123',
    ])->save();

    return $suscripcion->refresh();
}

it('abre la prueba gratuita al dar de alta al cliente', function (): void {
    $suscripcion = suscripcionDeAcme();

    expect($suscripcion->status)->toBe(SubscriptionStatus::Trialing)
        ->and($suscripcion->onTrial())->toBeTrue()
        ->and($suscripcion->trial_ends_at?->isFuture())->toBeTrue()
        ->and($suscripcion->plan?->code())->toBe(PlanCode::Starter)
        // Sin tarjeta no hay nada que cobrar todavia.
        ->and($suscripcion->canBeCharged())->toBeFalse();
})->group('tenancy');

it('cobra por sede activa con el minimo del plan como suelo', function (): void {
    $suscripcion = suscripcionDeAcme();

    // Starter: S/ 29 por sede, minimo 5. Con dos sedes se cobran cinco.
    expect($suscripcion->amountFor(2)->amount)->toBe('145.00')
        ->and($suscripcion->amountFor(8)->amount)->toBe('232.00');
})->group('tenancy');

it('emite el periodo con las sedes que tiene el cliente y no lo emite dos veces', function (): void {
    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    $inicio = CarbonImmutable::parse('2026-10-01', 'UTC');

    $tenant->run(function (): void {
        Site::factory()->count(7)->create();
    });

    $suscripcion = listaParaCobrar($inicio);

    $sedes = $tenant->run(static fn (): int => Site::query()->withoutGlobalScopes()->count());
    $emitir = resolve(IssueInvoice::class);

    $factura = $emitir($suscripcion, $sedes, $inicio->addMonth());

    expect($factura->billed_sites)->toBe(7)
        ->and($factura->amount)->toBe('203.00')
        ->and($factura->status)->toBe(InvoiceStatus::Pending);

    // Dos pasadas del comando el mismo dia no cobran dos veces el mes.
    $repetida = $emitir($suscripcion, $sedes, $inicio->addMonth());

    expect($repetida->id)->toBe($factura->id)
        ->and(Invoice::query()->count())->toBe(1);
})->group('tenancy');

it('cobra, deja al cliente activo y abre el periodo siguiente', function (): void {
    $inicio = CarbonImmutable::parse('2026-10-01', 'UTC');
    $suscripcion = listaParaCobrar($inicio);

    $factura = resolve(IssueInvoice::class)($suscripcion, 5, $inicio->addMonth());

    // Una pasarela que cobra siempre: lo que se prueba aqui es lo que pasa
    // DESPUES del cobro, no como se habla con Culqi.
    app()->instance(BillingGateway::class, new class implements BillingGateway
    {
        public function name(): string
        {
            return 'fake';
        }

        public function storeCard(string $token, string $email, string $reference): string
        {
            return 'crd_fake';
        }

        public function charge(ChargeRequest $request): ChargeResult
        {
            return ChargeResult::paid('chr_fake_1');
        }
    });

    $cobrada = resolve(ChargeInvoice::class)($factura, $inicio->addMonth());

    expect($cobrada->status)->toBe(InvoiceStatus::Paid)
        ->and($cobrada->external_id)->toBe('chr_fake_1')
        ->and($cobrada->paid_at)->not->toBeNull();

    $suscripcion->refresh();

    expect($suscripcion->status)->toBe(SubscriptionStatus::Active)
        // El periodo siguiente arranca donde termino el anterior, no «hoy»:
        // un cobro que se retrasa no le quita dias al cliente.
        ->and($suscripcion->current_period_start?->toDateString())->toBe($factura->period_end->toDateString());

    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    expect($tenant->fresh()?->status)->toBeInstanceOf(Active::class);
})->group('tenancy');

it('reintenta un rechazo antes de marcar moroso a nadie', function (): void {
    config()->set('billing.retry_days', [3]);

    $inicio = CarbonImmutable::parse('2026-10-01', 'UTC');
    $suscripcion = listaParaCobrar($inicio);
    $factura = resolve(IssueInvoice::class)($suscripcion, 5, $inicio->addMonth());

    app()->instance(BillingGateway::class, new class implements BillingGateway
    {
        public function name(): string
        {
            return 'fake';
        }

        public function storeCard(string $token, string $email, string $reference): string
        {
            return 'crd_fake';
        }

        public function charge(ChargeRequest $request): ChargeResult
        {
            return ChargeResult::declined('Tarjeta sin fondos.');
        }
    });

    $cobrar = resolve(ChargeInvoice::class);
    $ahora = $inicio->addMonth();

    // Primer rechazo: queda reintento, el cliente sigue operando.
    $primera = $cobrar($factura, $ahora);

    expect($primera->status)->toBe(InvoiceStatus::Failed)
        ->and($primera->failure_reason)->toBe('Tarjeta sin fondos.')
        ->and($primera->retry_after?->toDateString())->toBe($ahora->addDays(3)->toDateString());

    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    expect($tenant->fresh()?->status)->not->toBeInstanceOf(PastDue::class);

    // Segundo rechazo: ya no queda reintento, y recien ahi es moroso.
    $segunda = $cobrar($primera, $ahora->addDays(3));

    expect($segunda->attempts)->toBe(2)
        ->and($segunda->retry_after)->toBeNull()
        ->and($tenant->fresh()?->status)->toBeInstanceOf(PastDue::class);
})->group('tenancy');

it('no cobra ni marca moroso a quien paga por transferencia', function (): void {
    $inicio = CarbonImmutable::parse('2026-10-01', 'UTC');

    $suscripcion = suscripcionDeAcme();
    $suscripcion->forceFill([
        'status' => SubscriptionStatus::Active,
        'trial_ends_at' => $inicio->subDay(),
        'current_period_start' => $inicio,
        'current_period_end' => $inicio->addMonth(),
        // Sin tarjeta: es el modo manual.
        'card_reference' => null,
    ])->save();

    $factura = resolve(IssueInvoice::class)($suscripcion->refresh(), 5, $inicio->addMonth());
    $resultado = resolve(ChargeInvoice::class)($factura, $inicio->addMonth());

    /** @var Tenant $tenant */
    $tenant = $this->tenant;

    expect($resultado->status)->toBe(InvoiceStatus::Pending)
        ->and($resultado->attempts)->toBe(0)
        ->and($tenant->fresh()?->status)->not->toBeInstanceOf(PastDue::class);
})->group('tenancy');

it('usa la pasarela manual mientras no haya llaves, y avisa si se elige Culqi sin ellas', function (): void {
    config()->set('billing.gateway', 'manual');
    expect(resolve(BillingGatewayFactory::class)->make())->toBeInstanceOf(ManualBillingGateway::class);

    // Elegir Culqi sin llaves falla AL CONSTRUIR, no en el primer cobro.
    config()->set('billing.gateway', 'culqi');
    config()->set('billing.culqi.secret_key');
    expect(fn (): BillingGateway => resolve(BillingGatewayFactory::class)->make())
        ->toThrow(BillingNotConfigured::class);

    config()->set('billing.culqi.secret_key', 'sk_test_loquesea');
    expect(resolve(BillingGatewayFactory::class)->make())->toBeInstanceOf(CulqiGateway::class);
})->group('tenancy');

it('manda a Culqi el importe en centimos y entiende un rechazo', function (): void {
    config()->set('billing.gateway', 'culqi');
    config()->set('billing.culqi.secret_key', 'sk_test_loquesea');

    $inicio = CarbonImmutable::parse('2026-10-01', 'UTC');
    $suscripcion = listaParaCobrar($inicio);
    $factura = resolve(IssueInvoice::class)($suscripcion, 5, $inicio->addMonth());

    Http::fake([
        '*/charges' => Http::response(['id' => 'chr_test_9'], 201),
    ]);

    resolve(ChargeInvoice::class)($factura, $inicio->addMonth());

    Http::assertSent(
        // S/ 145.00 son 14500 centimos enteros. Culqi no acepta decimales.
        fn ($request): bool => $request['amount'] === 14500
        && $request['currency_code'] === 'PEN'
        && $request['source_id'] === 'crd_test_123'
        && $request->hasHeader('Authorization', 'Bearer sk_test_loquesea'));

})->group('tenancy');

it('entiende un rechazo de Culqi sin tratarlo como un error del sistema', function (): void {
    config()->set('billing.gateway', 'culqi');
    config()->set('billing.culqi.secret_key', 'sk_test_loquesea');

    $inicio = CarbonImmutable::parse('2026-10-01', 'UTC');
    $suscripcion = listaParaCobrar($inicio);
    $factura = resolve(IssueInvoice::class)($suscripcion, 5, $inicio->addMonth());

    // Culqi responde los rechazos con 4xx y un `card_error` con el texto que
    // hay que ensenarle a la persona.
    Http::fake([
        '*/charges' => Http::response([
            'object' => 'error',
            'type' => 'card_error',
            'user_message' => 'Tu tarjeta no tiene saldo.',
        ], 400),
    ]);

    $rechazada = resolve(ChargeInvoice::class)($factura, $inicio->addMonth());

    expect($rechazada->status)->toBe(InvoiceStatus::Failed)
        ->and($rechazada->failure_reason)->toBe('Tu tarjeta no tiene saldo.')
        ->and($rechazada->attempts)->toBe(1);
})->group('tenancy');

it('cambiar de plan conserva el precio que se firmo', function (): void {
    /** @var Tenant $tenant */
    $tenant = $this->tenant;

    resolve(StartSubscription::class)($tenant, PlanCode::Pro, BillingCycle::Yearly);

    $suscripcion = suscripcionDeAcme();

    expect($suscripcion->price_per_site)->toBe('49.00')
        ->and($suscripcion->cycle)->toBe(BillingCycle::Yearly)
        // El anual cobra diez meses, no doce: dos de descuento (sec. 3.6).
        ->and($suscripcion->amountFor(1)->amount)->toBe('490.00')
        // Y sigue siendo una sola suscripcion, no dos.
        ->and(Subscription::query()->where('tenant_id', $tenant->id)->count())->toBe(1);
})->group('tenancy');
