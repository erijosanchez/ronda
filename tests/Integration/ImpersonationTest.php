<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Ronda\Identity\Domain\Models\User;
use Ronda\Notifications\Infrastructure\Notifications\OperationalNotification;
use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Actions\StartImpersonation;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Exceptions\CannotImpersonate;
use Ronda\Platform\Domain\Models\ImpersonationEntry;
use Ronda\Platform\Domain\Models\PlatformUser;
use Ronda\Platform\Domain\Models\Tenant;
use Ronda\Platform\Presentation\Livewire\BackOfficeTenant;

// Suplantacion auditada. RONDA-PLAN-MAESTRO.md sec. 15.4
//
// Las cuatro condiciones del plan, cada una con su prueba: motivo obligatorio,
// limite de tiempo, registro que no se borra y aviso al cliente. Lo que se
// prueba no es que soporte pueda entrar —eso es facil— sino que no pueda
// entrar en silencio.

const CLAVE_SUPLANTACION = 'una-contrasena-larga-de-prueba';

beforeEach(function (): void {
    $this->soporte = PlatformUser::query()->create([
        'name' => 'Rosa de Soporte',
        'email' => 'rosa@ronda.test',
        'password' => CLAVE_SUPLANTACION,
        'is_active' => true,
        'two_factor_secret' => 'lo-que-sea',
        'two_factor_confirmed_at' => now(),
    ])->refresh();

    $this->tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Acme',
        slug: 'acme',
        domain: 'acme.ronda.test',
        ownerName: 'Duena de Acme',
        ownerEmail: 'owner@acme.test',
        ownerPassword: CLAVE_SUPLANTACION,
    ))->fresh();
});

afterEach(function (): void {
    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    dropTenantDatabase($tenant);
});

function duenaDeAcmeSuplantada(): User
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;

    return $tenant->run(
        static fn (): User => User::query()->where('email', 'owner@acme.test')->firstOrFail(),
    );
}

/**
 * Arranca una suplantacion y devuelve la direccion de un solo uso.
 */
function entrarComoDuena(string $motivo = 'Ticket 128: no le aparece el reporte de ayer'): string
{
    /** @var PlatformUser $soporte */
    $soporte = test()->soporte;
    /** @var Tenant $tenant */
    $tenant = test()->tenant;

    return resolve(StartImpersonation::class)(
        actor: $soporte,
        tenant: $tenant,
        userId: (int) duenaDeAcmeSuplantada()->id,
        reason: $motivo,
        ip: '190.12.0.1',
    );
}

it('exige un motivo que explique algo', function (): void {
    expect(fn (): string => entrarComoDuena('revisar'))
        ->toThrow(CannotImpersonate::class);

    // Y no queda nada anotado: no hubo acceso.
    expect(ImpersonationEntry::query()->count())->toBe(0);
})->group('security');

it('anota quien entro, a quien, por que y hasta cuando', function (): void {
    Notification::fake();

    $antes = CarbonImmutable::now('UTC');
    entrarComoDuena();

    $entrada = ImpersonationEntry::query()->firstOrFail();

    /** @var PlatformUser $soporte */
    $soporte = $this->soporte;

    expect($entrada->platform_user_id)->toBe($soporte->getKey())
        ->and($entrada->impersonated_user_email)->toBe('owner@acme.test')
        ->and($entrada->reason)->toContain('Ticket 128')
        ->and($entrada->ip_address)->toBe('190.12.0.1')
        ->and($entrada->ended_at)->toBeNull()
        // Media hora por defecto, contada desde ahora. La diferencia va del
        // instante anterior al vencimiento: al reves, Carbon la da negativa.
        ->and($antes->diffInMinutes($entrada->expires_at))->toBeGreaterThan(25)
        ->and($antes->diffInMinutes($entrada->expires_at))->toBeLessThan(35);
})->group('security');

it('avisa al cliente en el momento, no despues', function (): void {
    Notification::fake();

    entrarComoDuena();

    $duena = duenaDeAcmeSuplantada();

    Notification::assertSentTo($duena, OperationalNotification::class);
})->group('security');

it('deja entrar por el vale una sola vez', function (): void {
    Notification::fake();

    $destino = entrarComoDuena();
    $ruta = parse_url($destino, PHP_URL_PATH);

    $this->get('http://acme.ronda.test'.$ruta)->assertRedirect();

    expect(auth()->check())->toBeTrue()
        ->and(auth()->user()?->email)->toBe('owner@acme.test')
        ->and(session('impersonation'))->toHaveKey('entry');

    // El vale ya se gasto: el mismo enlace no vuelve a servir.
    auth()->logout();
    session()->flush();

    $this->get('http://acme.ronda.test'.$ruta)->assertForbidden();
})->group('security');

it('ensena el aviso de que soporte esta dentro', function (): void {
    Notification::fake();

    $destino = entrarComoDuena();
    $this->get('http://acme.ronda.test'.parse_url($destino, PHP_URL_PATH));

    $this->get('http://acme.ronda.test/panel')
        ->assertOk()
        ->assertSee(__('Ronda support is inside this account.'))
        ->assertSee('Ticket 128');
})->group('security');

it('cierra sola la sesion cuando se acaba el tiempo', function (): void {
    Notification::fake();

    $destino = entrarComoDuena();
    $this->get('http://acme.ronda.test'.parse_url($destino, PHP_URL_PATH));

    $this->get('http://acme.ronda.test/panel')->assertOk();

    // Media hora despues, sin que nadie pulse nada.
    CarbonImmutable::setTestNow(CarbonImmutable::now('UTC')->addMinutes(31));

    $this->get('http://acme.ronda.test/panel')
        ->assertRedirect('http://acme.ronda.test/login');

    expect(auth()->check())->toBeFalse()
        ->and(ImpersonationEntry::query()->firstOrFail()->ended_at)->not->toBeNull();

    CarbonImmutable::setTestNow();
})->group('security');

it('sale y lo anota cuando se pulsa el boton', function (): void {
    Notification::fake();

    $destino = entrarComoDuena();
    $this->get('http://acme.ronda.test'.parse_url($destino, PHP_URL_PATH));

    $this->post('http://acme.ronda.test/suplantacion/salir')
        ->assertRedirect('http://acme.ronda.test/login');

    expect(auth()->check())->toBeFalse()
        ->and(ImpersonationEntry::query()->firstOrFail()->ended_at)->not->toBeNull();
})->group('security');

it('no deja entrar con un vale inventado', function (): void {
    $this->get('http://acme.ronda.test/suplantacion/'.str_repeat('a', 64))
        ->assertForbidden();

    expect(auth()->check())->toBeFalse();
})->group('security');

it('pide el motivo tambien en la pantalla', function (): void {
    Notification::fake();

    /** @var PlatformUser $soporte */
    $soporte = $this->soporte;
    /** @var Tenant $tenant */
    $tenant = $this->tenant;

    $this->actingAs($soporte, 'platform');

    Livewire::test(BackOfficeTenant::class, ['tenant' => $tenant])
        ->set('impersonateUserId', (string) duenaDeAcmeSuplantada()->id)
        ->set('reason', 'corto')
        ->call('impersonate')
        ->assertHasErrors('reason');

    expect(ImpersonationEntry::query()->count())->toBe(0);
})->group('security');
