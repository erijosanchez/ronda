<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Evidence\Application\Actions\StoreEvidence;
use Ronda\Evidence\Domain\Models\Attachment;
use Ronda\Forms\Application\Actions\CreateTemplate;
use Ronda\Forms\Application\Actions\PublishTemplateVersion;
use Ronda\Forms\Application\Data\TemplateData;
use Ronda\Forms\Domain\ValueObjects\FormSchema;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\RoleName;
use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Models\Tenant;
use Ronda\Scheduling\Application\Actions\CreateSchedule;
use Ronda\Scheduling\Application\Actions\MaterializeObligations;
use Ronda\Scheduling\Application\Data\ScheduleData;
use Ronda\Scheduling\Domain\Models\Obligation;
use Ronda\Scheduling\Domain\ScheduleScope;
use Ronda\Scheduling\Domain\States\Fulfilled;
use Ronda\Submissions\Domain\Models\Submission;
use Tests\Support\EvidenceFixtures;

// La cola de envio del telefono. RONDA-PLAN-MAESTRO.md sec. 13.3
//
// Lo que se llena sin senal entra por esta puerta cuando vuelve la red. Lo que
// importa: que un reintento no duplique la entrega, que la frontera por sede
// siga valiendo, y que la cola sepa cuando dejar de insistir.
//
// El lado del navegador (guardar en IndexedDB, interceptar el envio, drenar al
// volver la senal) no se puede probar desde PHP: hay que verlo en un movil.

const CLAVE_COLA = 'una-contrasena-larga-de-prueba';

beforeEach(function (): void {
    $this->tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Acme',
        slug: 'acme',
        domain: 'acme.ronda.test',
        ownerName: 'Duena de Acme',
        ownerEmail: 'owner@acme.test',
        ownerPassword: CLAVE_COLA,
    ));

    Storage::fake(StoreEvidence::DISK);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-15 20:00', 'UTC'));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();

    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    dropTenantDatabase($tenant);
});

function enAcmeCola(Closure $fn): mixed
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;

    return $tenant->run($fn);
}

/**
 * Obligacion del 15 de septiembre con monto obligatorio y foto opcional, y la
 * encargada de esa sede.
 *
 * @return array{0: int, 1: string}
 */
function obligacionParaLaCola(): array
{
    return enAcmeCola(function (): array {
        $sede = Site::factory()->create();
        $owner = User::query()->where('email', 'owner@acme.test')->firstOrFail();

        $plantilla = resolve(CreateTemplate::class)(new TemplateData(code: 'ARQ', name: 'Arqueo'));
        resolve(PublishTemplateVersion::class)($plantilla, FormSchema::fromArray([
            ['key' => 'monto', 'type' => 'money', 'label' => 'Monto', 'required' => true, 'reportable' => true],
            ['key' => 'foto', 'type' => 'photo', 'label' => 'Foto'],
        ]), $owner);

        $programacion = resolve(CreateSchedule::class)(new ScheduleData(
            templateId: $plantilla->id,
            name: 'Arqueo diario',
            scope: ScheduleScope::Sites,
            rrule: 'FREQ=DAILY',
            windowStart: '08:00',
            windowEnd: '18:00',
            startsOn: '2026-09-01',
            skipHolidays: false,
            siteIds: [$sede->id],
        ));
        resolve(MaterializeObligations::class)($programacion, '2026-09-15', '2026-09-15');

        $encargada = User::create(['name' => 'Encargada', 'email' => 'encargada@acme.test', 'password' => CLAVE_COLA]);
        $encargada->assignRole(RoleName::SiteManager->value);
        $encargada->sites()->attach($sede->id);

        return [Obligation::query()->where('site_id', $sede->id)->firstOrFail()->id, 'encargada@acme.test'];
    });
}

function entrarComo(string $email): void
{
    test()->post('http://acme.ronda.test/logout');
    test()->post('http://acme.ronda.test/login', ['email' => $email, 'password' => CLAVE_COLA]);
}

/**
 * @param  array<string, mixed>  $cuerpo
 */
function entregarDesdeLaCola(int $obligacionId, array $cuerpo): TestResponse
{
    return test()->postJson('http://acme.ronda.test/pendientes/'.$obligacionId.'/entregar', $cuerpo);
}

it('acepta una entrega que estuvo esperando en el telefono, con su foto', function (): void {
    [$obligacionId, $email] = obligacionParaLaCola();
    entrarComo($email);

    $respuesta = entregarDesdeLaCola($obligacionId, [
        'client_token' => 'token-del-telefono-1',
        'answers' => ['monto' => '320.50'],
        'evidence' => [
            'foto' => [[
                'name' => 'arqueo.jpg',
                'data' => base64_encode(EvidenceFixtures::jpeg()),
                'latitude' => '-12.0464000',
                'longitude' => '-77.0428000',
            ]],
        ],
    ]);

    $respuesta->assertOk()->assertJsonPath('code', 'submitted');

    enAcmeCola(function () use ($obligacionId): void {
        $envio = Submission::query()->sole();
        $adjunto = Attachment::query()->sole();

        expect($envio->client_token)->toBe('token-del-telefono-1')
            ->and($envio->data['monto'])->toBe('320.50')
            ->and($envio->data['foto'])->toBe([(string) $adjunto->id])
            // La ubicacion es la del momento en que se lleno, no la de ahora.
            ->and($adjunto->location_source)->toBe('device')
            // Y la obligacion queda cumplida, como en cualquier entrega.
            ->and(Obligation::query()->findOrFail($obligacionId)->status)->toBeInstanceOf(Fulfilled::class);
    });
})->group('submissions');

it('reconoce el reintento del telefono en vez de duplicar la entrega', function (): void {
    // El caso real: la entrega llego, la respuesta se perdio en el tunel y el
    // telefono lo vuelve a intentar.
    [$obligacionId, $email] = obligacionParaLaCola();
    entrarComo($email);

    $cuerpo = ['client_token' => 'token-repetido', 'answers' => ['monto' => '100']];

    $primera = entregarDesdeLaCola($obligacionId, $cuerpo)->assertOk();
    $segunda = entregarDesdeLaCola($obligacionId, $cuerpo)->assertOk();

    expect($segunda->json('submission_id'))->toBe($primera->json('submission_id'));

    enAcmeCola(function (): void {
        expect(Submission::query()->count())->toBe(1);
    });
})->group('submissions');

it('rechaza la entrega de una sede que no se alcanza', function (): void {
    [$obligacionId] = obligacionParaLaCola();

    enAcmeCola(function (): void {
        $otra = User::create(['name' => 'Ajena', 'email' => 'ajena@acme.test', 'password' => CLAVE_COLA]);
        $otra->assignRole(RoleName::SiteManager->value);
        $otra->sites()->attach(Site::factory()->create()->id);
    });

    entrarComo('ajena@acme.test');

    entregarDesdeLaCola($obligacionId, [
        'client_token' => 'token-ajeno',
        'answers' => ['monto' => '100'],
    ])->assertForbidden();

    enAcmeCola(function (): void {
        expect(Submission::query()->count())->toBe(0);
    });
})->group('submissions');

it('dice a la cola que deje de insistir cuando el plazo ya cerro', function (): void {
    [$obligacionId, $email] = obligacionParaLaCola();
    entrarComo($email);

    // El telefono estuvo sin senal hasta pasado el cierre.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-16 12:00', 'UTC'));

    entregarDesdeLaCola($obligacionId, [
        'client_token' => 'token-tardio',
        'answers' => ['monto' => '100'],
    ])->assertStatus(409)->assertJsonPath('code', 'cannot_submit');
})->group('submissions');

it('devuelve los errores por campo cuando las respuestas no se sostienen', function (): void {
    [$obligacionId, $email] = obligacionParaLaCola();
    entrarComo($email);

    $respuesta = entregarDesdeLaCola($obligacionId, [
        'client_token' => 'token-invalido',
        'answers' => ['monto' => 'cien soles'],
    ]);

    $respuesta->assertStatus(422)
        ->assertJsonPath('code', 'invalid_answers')
        ->assertJsonStructure(['errors' => ['monto']]);

    enAcmeCola(function (): void {
        expect(Submission::query()->count())->toBe(0);
    });
})->group('submissions');

it('exige la huella del dispositivo', function (): void {
    // Sin huella no hay forma de reconocer un reintento, y la cola duplicaria
    // entregas en cuanto se perdiera una respuesta.
    [$obligacionId, $email] = obligacionParaLaCola();
    entrarComo($email);

    entregarDesdeLaCola($obligacionId, ['answers' => ['monto' => '100']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('client_token');
})->group('submissions');

it('no deja entregar sin sesion', function (): void {
    [$obligacionId] = obligacionParaLaCola();

    // La cola reintenta desde un telefono cuya sesion caduco: tiene que
    // recibir un no, no colar la entrega.
    $respuesta = entregarDesdeLaCola($obligacionId, [
        'client_token' => 'token-sin-sesion',
        'answers' => ['monto' => '100'],
    ]);

    expect($respuesta->status())->toBeIn([401, 403, 419]);

    enAcmeCola(function (): void {
        expect(Submission::query()->count())->toBe(0);
    });
})->group('submissions');
