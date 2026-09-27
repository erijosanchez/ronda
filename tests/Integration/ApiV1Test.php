<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Database\Seeders\PlanSeeder;
use Ronda\Api\Application\Actions\IssueApiToken;
use Ronda\Api\Domain\ApiScope;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Forms\Application\Actions\CreateTemplate;
use Ronda\Forms\Application\Actions\PublishTemplateVersion;
use Ronda\Forms\Application\Data\TemplateData;
use Ronda\Forms\Domain\ValueObjects\FormSchema;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\RoleName;
use Ronda\Platform\Application\Actions\ChangeTenantPlan;
use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Models\Tenant;
use Ronda\Platform\Domain\PlanCode;
use Ronda\Scheduling\Application\Actions\CreateSchedule;
use Ronda\Scheduling\Application\Actions\MaterializeObligations;
use Ronda\Scheduling\Application\Data\ScheduleData;
use Ronda\Scheduling\Domain\Models\Obligation;
use Ronda\Scheduling\Domain\ScheduleScope;
use Ronda\Submissions\Domain\Models\Submission;

// API publica v1. RONDA-PLAN-MAESTRO.md sec. 13.1
//
// Lo que se prueba es la puerta, no los datos: sin token no se entra, sin
// alcance tampoco, sin plan tampoco, y lo que se ve sigue siendo lo que la
// frontera por sede deja ver. Que la API devuelva JSON es lo facil.

const CLAVE_API = 'una-contrasena-larga-de-prueba';

beforeEach(function (): void {
    $this->seed(PlanSeeder::class);

    $this->tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Acme',
        slug: 'acme',
        domain: 'acme.ronda.test',
        ownerName: 'Duena de Acme',
        ownerEmail: 'owner@acme.test',
        ownerPassword: CLAVE_API,
    ))->fresh();

    // La API es de Pro (sec. 3.6). Con Starter ni se entra, y eso tiene su
    // propia prueba mas abajo.
    resolve(ChangeTenantPlan::class)($this->tenant, PlanCode::Pro);
    app()->forgetScopedInstances();
});

afterEach(function (): void {
    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    dropTenantDatabase($tenant);
});

function enAcmeApi(Closure $fn): mixed
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;

    return $tenant->run($fn);
}

/**
 * Un token de la duena con los alcances que se le pidan.
 *
 * @param  list<ApiScope>  $scopes
 */
function tokenDeAcme(array $scopes): string
{
    return enAcmeApi(static function () use ($scopes): string {
        $duena = User::query()->where('email', 'owner@acme.test')->firstOrFail();

        return resolve(IssueApiToken::class)($duena, 'Prueba', $scopes)['token'];
    });
}

function urlApi(string $ruta): string
{
    return 'http://acme.ronda.test/api/v1'.$ruta;
}

it('no deja entrar sin token', function (): void {
    $this->getJson(urlApi('/sites'))->assertUnauthorized();
})->group('api');

it('no deja entrar con un token inventado', function (): void {
    $this->withToken('no-existe-este-token')
        ->getJson(urlApi('/sites'))
        ->assertUnauthorized();
})->group('api');

it('responde con el token correcto y con la forma acordada', function (): void {
    enAcmeApi(function (): void {
        Site::factory()->count(3)->create();
    });

    $token = tokenDeAcme([ApiScope::SitesRead]);

    $respuesta = $this->withToken($token)->getJson(urlApi('/sites'))->assertOk();

    expect($respuesta->json('data'))->toHaveCount(3)
        ->and($respuesta->json('data.0'))->toHaveKeys(['id', 'code', 'name', 'timezone'])
        // Cursor, no numero de pagina (sec. 13.1).
        ->and($respuesta->json('meta'))->toHaveKey('next_cursor');
})->group('api');

it('rechaza al token que no tiene el alcance', function (): void {
    // Un token que solo lee sedes no lee envios, aunque su duena si pueda.
    $token = tokenDeAcme([ApiScope::SitesRead]);

    $this->withToken($token)
        ->getJson(urlApi('/submissions'))
        ->assertForbidden()
        ->assertJsonPath('code', 'missing_scope')
        ->assertJsonPath('required_scope', ApiScope::SubmissionsRead->value);
})->group('api');

it('no deja entregar a un token de solo lectura', function (): void {
    $token = tokenDeAcme([ApiScope::SubmissionsRead]);

    $this->withToken($token)
        ->postJson(urlApi('/submissions'), ['obligation_id' => 1])
        ->assertForbidden()
        ->assertJsonPath('required_scope', ApiScope::SubmissionsWrite->value);
})->group('api');

it('cierra la API cuando el plan no la incluye', function (): void {
    $token = tokenDeAcme([ApiScope::SitesRead]);

    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    resolve(ChangeTenantPlan::class)($tenant, PlanCode::Starter);
    app()->forgetScopedInstances();

    $this->withToken($token)
        ->getJson(urlApi('/sites'))
        ->assertForbidden()
        ->assertJsonPath('code', 'plan_without_api');
})->group('api');

it('devuelve las cabeceras de cuota en cada respuesta', function (): void {
    $token = tokenDeAcme([ApiScope::SitesRead]);

    $this->withToken($token)
        ->getJson(urlApi('/sites'))
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit')
        ->assertHeader('X-RateLimit-Remaining');
})->group('api');

it('corta cuando se pasa de la cuota', function (): void {
    config()->set('api.rate_limit_per_minute', 2);

    $token = tokenDeAcme([ApiScope::SitesRead]);

    $this->withToken($token)->getJson(urlApi('/sites'))->assertOk();
    $this->withToken($token)->getJson(urlApi('/sites'))->assertOk();

    $this->withToken($token)
        ->getJson(urlApi('/sites'))
        ->assertStatus(429)
        ->assertJsonPath('code', 'rate_limited')
        ->assertHeader('Retry-After');
})->group('api');

it('respeta la frontera por sede', function (): void {
    /** @var array{0: int, 1: string} $datos */
    $datos = enAcmeApi(static function (): array {
        $suya = Site::factory()->create(['name' => 'La suya']);
        Site::factory()->create(['name' => 'La de otra persona']);

        $encargada = User::create([
            'name' => 'Encargada',
            'email' => 'encargada@acme.test',
            'password' => CLAVE_API,
        ]);
        $encargada->assignRole(RoleName::SiteManager->value);
        $encargada->sites()->attach($suya->id);

        $token = resolve(IssueApiToken::class)(
            $encargada->fresh(),
            'Token de la encargada',
            [ApiScope::SitesRead],
        )['token'];

        return [(int) $suya->id, $token];
    });

    $respuesta = $this->withToken($datos[1])->getJson(urlApi('/sites'))->assertOk();

    // Ve la suya y solo la suya: el token no amplia lo que su duena alcanza.
    expect($respuesta->json('data'))->toHaveCount(1)
        ->and($respuesta->json('data.0.id'))->toBe($datos[0]);
})->group('api');

it('entrega un reporte por la API, con la misma Action de siempre', function (): void {
    $obligacion = enAcmeApi(static function (): Obligation {
        $sede = Site::factory()->create();
        $duena = User::query()->where('email', 'owner@acme.test')->firstOrFail();

        $plantilla = resolve(CreateTemplate::class)(new TemplateData(code: 'ARQUEO', name: 'Arqueo'));
        resolve(PublishTemplateVersion::class)($plantilla, FormSchema::fromArray([
            ['key' => 'monto', 'type' => 'money', 'label' => 'Monto', 'required' => true, 'reportable' => true],
        ]), $duena);

        $programacion = resolve(CreateSchedule::class)(new ScheduleData(
            templateId: $plantilla->id,
            name: 'Arqueo diario',
            scope: ScheduleScope::Sites,
            rrule: 'FREQ=DAILY',
            windowStart: '00:00',
            windowEnd: '23:59',
            startsOn: CarbonImmutable::now('UTC')->subDay()->toDateString(),
            siteIds: [$sede->id],
        ));

        $hoy = CarbonImmutable::now('America/Lima')->toDateString();
        resolve(MaterializeObligations::class)($programacion, $hoy, $hoy);

        return Obligation::query()->where('site_id', $sede->id)->firstOrFail();
    });

    $token = tokenDeAcme([ApiScope::SubmissionsWrite]);

    $respuesta = $this->withToken($token)->postJson(urlApi('/submissions'), [
        'obligation_id' => $obligacion->id,
        'answers' => ['monto' => '1520.40'],
        'client_token' => 'token-de-la-integracion-1',
    ])->assertCreated();

    $id = $respuesta->json('id');

    enAcmeApi(function () use ($id, $obligacion): void {
        $envio = Submission::query()->findOrFail($id);

        expect($envio->data)->toBe(['monto' => '1520.40'])
            // La obligacion queda cumplida: es la misma Action que usa el
            // telefono, no un camino paralelo.
            ->and($obligacion->fresh()?->submission_id)->toBe($envio->id);
    });

    // Y repetir la llamada con la misma huella no crea un segundo envio.
    $this->withToken($token)->postJson(urlApi('/submissions'), [
        'obligation_id' => $obligacion->id,
        'answers' => ['monto' => '1520.40'],
        'client_token' => 'token-de-la-integracion-1',
    ])->assertCreated();

    enAcmeApi(function (): void {
        expect(Submission::query()->count())->toBe(1);
    });
})->group('api');

it('rechaza respuestas que no se sostienen contra el esquema', function (): void {
    $obligacion = enAcmeApi(static function (): Obligation {
        $sede = Site::factory()->create();
        $duena = User::query()->where('email', 'owner@acme.test')->firstOrFail();

        $plantilla = resolve(CreateTemplate::class)(new TemplateData(code: 'ARQUEO', name: 'Arqueo'));
        resolve(PublishTemplateVersion::class)($plantilla, FormSchema::fromArray([
            ['key' => 'monto', 'type' => 'money', 'label' => 'Monto', 'required' => true, 'reportable' => true],
        ]), $duena);

        $programacion = resolve(CreateSchedule::class)(new ScheduleData(
            templateId: $plantilla->id,
            name: 'Arqueo diario',
            scope: ScheduleScope::Sites,
            rrule: 'FREQ=DAILY',
            windowStart: '00:00',
            windowEnd: '23:59',
            startsOn: CarbonImmutable::now('UTC')->subDay()->toDateString(),
            siteIds: [$sede->id],
        ));

        $hoy = CarbonImmutable::now('America/Lima')->toDateString();
        resolve(MaterializeObligations::class)($programacion, $hoy, $hoy);

        return Obligation::query()->where('site_id', $sede->id)->firstOrFail();
    });

    $token = tokenDeAcme([ApiScope::SubmissionsWrite]);

    // Falta el campo obligatorio: 422 con el detalle, no un 500.
    $this->withToken($token)->postJson(urlApi('/submissions'), [
        'obligation_id' => $obligacion->id,
        'answers' => [],
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'invalid_answers');
})->group('api');

it('no filtra por columnas que no estan en la lista blanca', function (): void {
    enAcmeApi(function (): void {
        Site::factory()->count(2)->create();
    });

    $token = tokenDeAcme([ApiScope::SitesRead]);

    // `filter[id]` no esta permitido: la API responde 400 en vez de dejar
    // consultar la base por donde se quiera.
    $this->withToken($token)
        ->getJson(urlApi('/sites?filter[id]=1'))
        ->assertStatus(400);
})->group('api');
