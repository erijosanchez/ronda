<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Livewire\Livewire;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Forms\Application\Actions\CreateTemplate;
use Ronda\Forms\Application\Actions\PublishTemplateVersion;
use Ronda\Forms\Application\Data\TemplateData;
use Ronda\Forms\Domain\Models\Template;
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
use Ronda\Scheduling\Domain\States\Pending;
use Ronda\Submissions\Application\Actions\SubmitReport;
use Ronda\Submissions\Domain\Exceptions\CannotSubmit;
use Ronda\Submissions\Domain\Exceptions\InvalidAnswers;
use Ronda\Submissions\Domain\Models\Submission;
use Ronda\Submissions\Domain\Models\SubmissionValue;
use Ronda\Submissions\Presentation\Livewire\PendingObligations;
use Ronda\Submissions\Presentation\Livewire\SubmissionForm;

// La entrega de un reporte. ADR 0008 y ADR 0012.
//
// Aqui se juntan las cuatro primeras piezas del motor. Lo que se prueba es que
// las tres tablas que toca la entrega cuadran entre si, y que una sede no puede
// cumplir la obligacion de otra.

const CLAVE_ENTREGA = 'una-contrasena-larga-de-prueba';

beforeEach(function (): void {
    $this->tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Acme',
        slug: 'acme',
        domain: 'acme.ronda.test',
        ownerName: 'Duena de Acme',
        ownerEmail: 'owner@acme.test',
        ownerPassword: CLAVE_ENTREGA,
    ));
});

afterEach(function (): void {
    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    dropTenantDatabase($tenant);
});

function enAcmeEntrega(Closure $fn): void
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;
    $tenant->run($fn);
}

/**
 * Plantilla de arqueo publicada, programada a diario, con la obligacion del 15
 * de septiembre de 2026 materializada para una sede. Ventana 08:00-18:00 hora
 * de Lima (13:00-23:00 UTC), 30 minutos de tolerancia.
 */
function obligacionDeArqueo(?Site $sede = null): Obligation
{
    $sede ??= Site::factory()->create();
    $owner = User::query()->where('email', 'owner@acme.test')->firstOrFail();

    $plantilla = resolve(CreateTemplate::class)(new TemplateData(code: 'ARQUEO-'.$sede->id, name: 'Arqueo'));
    resolve(PublishTemplateVersion::class)($plantilla, FormSchema::fromArray([
        ['key' => 'monto', 'type' => 'money', 'label' => 'Monto', 'required' => true, 'reportable' => true],
        ['key' => 'notas', 'type' => 'text', 'label' => 'Notas'],
    ]), $owner);

    $programacion = resolve(CreateSchedule::class)(new ScheduleData(
        templateId: $plantilla->id,
        name: 'Arqueo diario',
        scope: ScheduleScope::Sites,
        rrule: 'FREQ=DAILY',
        windowStart: '08:00',
        windowEnd: '18:00',
        startsOn: '2026-09-01',
        toleranceMinutes: 30,
        siteIds: [$sede->id],
    ));

    resolve(MaterializeObligations::class)($programacion, '2026-09-15', '2026-09-15');

    return Obligation::query()->where('site_id', $sede->id)->firstOrFail();
}

function encargadaDe(Site $sede, string $email = 'encargada@acme.test'): User
{
    $user = User::create(['name' => 'Encargada', 'email' => $email, 'password' => CLAVE_ENTREGA]);
    $user->assignRole(RoleName::SiteManager->value);
    $user->sites()->attach($sede->id);

    return $user->fresh();
}

function a(string $utc): CarbonImmutable
{
    return CarbonImmutable::parse($utc, 'UTC');
}

it('guarda el envio, replica lo reportable y cumple la obligacion en una sola pasada', function (): void {
    enAcmeEntrega(function (): void {
        $obligacion = obligacionDeArqueo();
        $autora = User::query()->where('email', 'owner@acme.test')->firstOrFail();

        $envio = resolve(SubmitReport::class)(
            $obligacion, $autora, ['monto' => '1520.40', 'notas' => 'sin novedad'], a('2026-09-15 20:00'),
        );

        // 1. El envio, con la version exacta con la que se respondio.
        expect($envio->data)->toBe(['monto' => '1520.40', 'notas' => 'sin novedad'])
            ->and($envio->template_version_id)->toBe(Template::query()->find($obligacion->template_id)->current_version_id)
            ->and($envio->is_late)->toBeFalse();

        // 2. Solo lo reportable, en su columna tipada.
        $valores = SubmissionValue::query()->where('submission_id', $envio->id)->get();
        expect($valores)->toHaveCount(1)
            ->and($valores->first()->field_key)->toBe('monto')
            ->and($valores->first()->value_numeric)->toBe('1520.4000');

        // 3. La obligacion, cumplida y enlazada.
        $obligacion->refresh();
        expect($obligacion->status)->toBeInstanceOf(Fulfilled::class)
            ->and($obligacion->submission_id)->toBe($envio->id);
    });
})->group('submissions');

it('marca la entrega como tardia pasado el vencimiento y dentro de la tolerancia', function (): void {
    enAcmeEntrega(function (): void {
        $obligacion = obligacionDeArqueo();
        $autora = User::query()->where('email', 'owner@acme.test')->firstOrFail();

        // Vence 23:00 UTC; cierra 23:30. A las 23:12 es tardia pero valida.
        $envio = resolve(SubmitReport::class)($obligacion, $autora, ['monto' => '10'], a('2026-09-15 23:12'));

        expect($envio->is_late)->toBeTrue()
            ->and($envio->minutes_late)->toBe(12);
    });
})->group('submissions');

it('rechaza la entrega antes de que abra la ventana', function (): void {
    enAcmeEntrega(function (): void {
        $obligacion = obligacionDeArqueo();
        $autora = User::query()->where('email', 'owner@acme.test')->firstOrFail();

        expect(fn () => resolve(SubmitReport::class)($obligacion, $autora, ['monto' => '10'], a('2026-09-15 12:59')))
            ->toThrow(CannotSubmit::class, 'todavia no esta abierta');
    });
})->group('submissions');

it('rechaza la entrega pasado el cierre aunque el job aun no la haya marcado', function (): void {
    // Entre el cierre y la siguiente pasada horaria del job, la obligacion
    // sigue `pending`. No por eso admite entregas.
    enAcmeEntrega(function (): void {
        $obligacion = obligacionDeArqueo();
        $autora = User::query()->where('email', 'owner@acme.test')->firstOrFail();

        expect($obligacion->status)->toBeInstanceOf(Pending::class);

        expect(fn () => resolve(SubmitReport::class)($obligacion, $autora, ['monto' => '10'], a('2026-09-15 23:31')))
            ->toThrow(CannotSubmit::class, 'cerro');
    });
})->group('submissions');

it('no cumple dos veces la misma obligacion', function (): void {
    enAcmeEntrega(function (): void {
        $obligacion = obligacionDeArqueo();
        $autora = User::query()->where('email', 'owner@acme.test')->firstOrFail();
        $entregar = resolve(SubmitReport::class);

        $entregar($obligacion, $autora, ['monto' => '10'], a('2026-09-15 20:00'));

        expect(fn () => $entregar($obligacion, $autora, ['monto' => '99'], a('2026-09-15 20:05')))
            ->toThrow(CannotSubmit::class, 'ya no esta pendiente');

        expect(Submission::query()->count())->toBe(1);
    });
})->group('submissions');

it('la base impide un segundo envio aunque se salte la Action', function (): void {
    // Ultima barrera: la restriccion unica de submissions.obligation_id.
    enAcmeEntrega(function (): void {
        $obligacion = obligacionDeArqueo();
        $autora = User::query()->where('email', 'owner@acme.test')->firstOrFail();
        $envio = resolve(SubmitReport::class)($obligacion, $autora, ['monto' => '10'], a('2026-09-15 20:00'));

        expect(fn () => Submission::create([
            ...$envio->only(['template_id', 'template_version_id', 'site_id', 'obligation_id', 'author_id']),
            'state' => 'submitted',
            'data' => [],
            'submitted_at' => now(),
        ]))->toThrow(UniqueConstraintViolationException::class);
    });
})->group('submissions');

it('no escribe nada si las respuestas no son validas', function (): void {
    // Transaccion entera o nada: ni envio, ni valores, ni obligacion cumplida.
    enAcmeEntrega(function (): void {
        $obligacion = obligacionDeArqueo();
        $autora = User::query()->where('email', 'owner@acme.test')->firstOrFail();

        expect(fn () => resolve(SubmitReport::class)($obligacion, $autora, ['notas' => 'falta el monto'], a('2026-09-15 20:00')))
            ->toThrow(InvalidAnswers::class);

        expect(Submission::query()->count())->toBe(0)
            ->and(SubmissionValue::query()->count())->toBe(0)
            ->and($obligacion->refresh()->status)->toBeInstanceOf(Pending::class);
    });
})->group('submissions');

it('deja entregar a la encargada de la sede', function (): void {
    enAcmeEntrega(function (): void {
        $sede = Site::factory()->create();
        $obligacion = obligacionDeArqueo($sede);
        $encargada = encargadaDe($sede);

        expect($encargada->can('submit', $obligacion))->toBeTrue();
    });
})->group('submissions');

it('no deja cumplir la obligacion de una sede que no es suya', function (): void {
    // Contraste con la prueba anterior: mismo rol, otra sede. Sin esto, cambiar
    // un id en la URL bastaria para cumplir la entrega de otro local.
    enAcmeEntrega(function (): void {
        $suya = Site::factory()->create();
        $ajena = Site::factory()->create();
        $obligacionAjena = obligacionDeArqueo($ajena);
        $encargada = encargadaDe($suya);

        expect($encargada->can('submit', $obligacionAjena))->toBeFalse();

        auth()->login($encargada);
        Livewire::test(SubmissionForm::class, ['obligation' => $obligacionAjena])->assertForbidden();
    });
})->group('submissions');

it('la lista de pendientes muestra solo las sedes del usuario', function (): void {
    enAcmeEntrega(function (): void {
        CarbonImmutable::setTestNow(a('2026-09-15 18:00'));

        $suya = Site::factory()->create(['name' => 'Sede Propia']);
        $ajena = Site::factory()->create(['name' => 'Sede Ajena']);
        obligacionDeArqueo($suya);
        obligacionDeArqueo($ajena);

        auth()->login(encargadaDe($suya));

        Livewire::test(PendingObligations::class)
            ->assertOk()
            ->assertSee('Sede Propia')
            ->assertDontSee('Sede Ajena');

        CarbonImmutable::setTestNow();
    });
})->group('submissions');

it('entrega desde la pantalla y vuelve a la lista', function (): void {
    enAcmeEntrega(function (): void {
        $sede = Site::factory()->create();
        $obligacion = obligacionDeArqueo($sede);
        $encargada = encargadaDe($sede);

        CarbonImmutable::setTestNow(a('2026-09-15 20:00'));
        auth()->login($encargada);

        Livewire::test(SubmissionForm::class, ['obligation' => $obligacion])
            ->set('answers.monto', '875.00')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('submissions.pending'));

        expect($obligacion->refresh()->status)->toBeInstanceOf(Fulfilled::class);

        CarbonImmutable::setTestNow();
    });
})->group('submissions');

it('pinta los errores del dominio junto a cada campo', function (): void {
    enAcmeEntrega(function (): void {
        $sede = Site::factory()->create();
        $obligacion = obligacionDeArqueo($sede);

        CarbonImmutable::setTestNow(a('2026-09-15 20:00'));
        auth()->login(encargadaDe($sede));

        Livewire::test(SubmissionForm::class, ['obligation' => $obligacion])
            ->set('answers.monto', 'mil soles')
            ->call('submit')
            ->assertHasErrors('answers.monto');

        CarbonImmutable::setTestNow();
    });
})->group('submissions');
