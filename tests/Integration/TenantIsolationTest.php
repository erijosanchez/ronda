<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Ronda\Identity\Domain\Models\User;
use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Models\Tenant;

// Aislamiento entre tenants. RONDA-PLAN-MAESTRO.md sec. 7.5
//
// Estas pruebas montan DOS clientes de verdad, con su base cada uno, y
// comprueban que ninguno alcanza los datos del otro. Es el invariante del
// ADR 0002 y la razon de que el modelo sea base-por-tenant.

/**
 * Job de prueba: anota que tenant y que usuarios ve al ejecutarse.
 *
 * Vive aqui y no en src/ porque no es codigo de produccion: existe solo para
 * ejercitar el QueueTenancyBootstrapper, que mete el tenant_id en el payload
 * y entra en contexto antes de correr el job (sec. 7.4).
 */
final class RecordVisibleTenantJob implements ShouldQueue
{
    use Queueable;

    /** @var array<string, mixed> */
    public static array $visto = [];

    public function handle(): void
    {
        self::$visto = [
            'tenant' => tenant('id'),
            'correos' => User::query()->orderBy('email')->pluck('email')->all(),
        ];
    }
}

function crearTenant(string $slug): Tenant
{
    return resolve(CreateTenant::class)(new CreateTenantData(
        name: ucfirst($slug),
        slug: $slug,
        domain: $slug.'.ronda.test',
        ownerName: 'Duena de '.$slug,
        ownerEmail: 'owner@'.$slug.'.test',
        ownerPassword: 'una-contrasena-larga-de-prueba',
    ));
}

beforeEach(function (): void {
    $this->a = crearTenant('alfa');
    $this->b = crearTenant('beta');
});

afterEach(function (): void {
    dropTenantDatabase($this->a);
    dropTenantDatabase($this->b);
});

it('no comparte usuarios entre las bases de dos tenants', function (): void {
    /** @var Tenant $a */
    $a = $this->a;
    /** @var Tenant $b */
    $b = $this->b;

    $a->run(function (): void {
        expect(User::query()->pluck('email')->all())->toBe(['owner@alfa.test']);
    });

    $b->run(function (): void {
        expect(User::query()->pluck('email')->all())->toBe(['owner@beta.test'])
            ->and(User::query()->where('email', 'owner@alfa.test')->exists())->toBeFalse(
                'El tenant beta alcanza un usuario de alfa: el aislamiento por base esta roto.',
            );
    });
})->group('tenancy');

it('rechaza las credenciales de un tenant en el dominio de otro', function (): void {
    // El caso que mas duele en una demo: el cliente A entra en la URL del
    // cliente B con su propio usuario. No existe alli, y punto.
    $this->from('http://beta.ronda.test/login')->post('http://beta.ronda.test/login', [
        'email' => 'owner@alfa.test',
        'password' => 'una-contrasena-larga-de-prueba',
    ]);

    $this->assertGuest();
    expect(session('errors')->first('email'))->toBe(__('auth.failed'));
})->group('tenancy');

it('ata la cookie de sesion al dominio exacto del tenant', function (): void {
    // Con SESSION_DOMAIN a null la cookie es host-only: el navegador la manda
    // a alfa.ronda.test y a ningun otro host. Si alguien la fijase al dominio
    // padre (`.ronda.test`), la sesion de un cliente viajaria a la URL de
    // cualquier otro, y como los ids de usuario empiezan en 1 en cada base,
    // el guard resolveria el usuario 1 del otro cliente.
    expect(config('session.domain'))->toBeNull(
        'SESSION_DOMAIN debe quedar vacio: un dominio padre comparte la cookie entre tenants.',
    );
})->group('tenancy');

it('cambia de contexto de tenant al cambiar de dominio', function (): void {
    /** @var Tenant $a */
    $a = $this->a;
    /** @var Tenant $b */
    $b = $this->b;

    $this->get('http://alfa.ronda.test/login')->assertOk();
    expect(tenant('id'))->toBe($a->id);

    // El cliente de pruebas reutiliza la misma sesion entre dominios; un
    // navegador no, porque SESSION_DOMAIN esta vacio y la cookie es host-only.
    // Sin vaciarla, EnsureSessionBelongsToTenant redirige, que es justo su
    // trabajo: esa mezcla de sesiones se prueba en CrossTenantRouteTest.
    $this->flushSession();

    $this->get('http://beta.ronda.test/login')->assertOk();
    expect(tenant('id'))->toBe($b->id);
})->group('tenancy');

it('un job encolado en el tenant A no puede leer datos del tenant B', function (): void {
    /** @var Tenant $a */
    $a = $this->a;

    $a->run(function (): void {
        dispatch(new RecordVisibleTenantJob);
    });

    /** @var Tenant $b */
    $b = $this->b;

    expect(RecordVisibleTenantJob::$visto['tenant'])->toBe($a->id)
        ->and(RecordVisibleTenantJob::$visto['tenant'])->not->toBe($b->id)
        ->and(RecordVisibleTenantJob::$visto['correos'])->toBe(['owner@alfa.test']);
})->group('tenancy');

it('no comparte la cache entre tenants', function (): void {
    /** @var Tenant $a */
    $a = $this->a;
    /** @var Tenant $b */
    $b = $this->b;

    $a->run(function (): void {
        Cache::put('clave-compartida', 'valor-de-alfa', 60);
    });

    $b->run(function (): void {
        expect(Cache::get('clave-compartida'))->toBeNull(
            'La cache filtra valores entre clientes: falta el prefijo por tenant.',
        );
    });
})->group('tenancy');
