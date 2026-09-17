<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Evidence\Application\Actions\StoreEvidence;
use Ronda\Evidence\Application\Data\EvidenceUpload;
use Ronda\Evidence\Domain\Models\Attachment;
use Ronda\Forms\Application\Actions\CreateTemplate;
use Ronda\Forms\Application\Actions\PublishTemplateVersion;
use Ronda\Forms\Application\Data\TemplateData;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Forms\Domain\ValueObjects\FormSchema;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\PermissionName;
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
use Ronda\Submissions\Application\Actions\SubmitReport;
use Ronda\Submissions\Domain\Exceptions\InvalidAnswers;
use Ronda\Submissions\Domain\Models\Submission;
use Ronda\Submissions\Domain\States\Approved;
use Ronda\Submissions\Domain\States\Rejected;
use Ronda\Submissions\Domain\States\Submitted;
use Ronda\Submissions\Domain\States\UnderReview;
use Ronda\Submissions\Presentation\Livewire\SubmissionDetail;
use Ronda\Workflow\Application\Actions\ApproveSubmission;
use Ronda\Workflow\Application\Actions\CorrectSubmission;
use Ronda\Workflow\Application\Actions\RejectSubmission;
use Ronda\Workflow\Application\Actions\TakeSubmission;
use Ronda\Workflow\Domain\Events\SubmissionApproved;
use Ronda\Workflow\Domain\Events\SubmissionRejected;
use Ronda\Workflow\Domain\Exceptions\CannotReview;
use Ronda\Workflow\Domain\Models\SubmissionRevision;
use Ronda\Workflow\Presentation\Livewire\ReviewInbox;
use Ronda\Workflow\Presentation\Livewire\ReviewPanel;
use Ronda\Workflow\Presentation\Livewire\SubmissionCorrectionForm;
use Tests\Support\EvidenceFixtures;

// Revision de envios. RONDA-PLAN-MAESTRO.md sec. 9.4
//
// Lo que importa aqui no es que los botones existan sino que el flujo no se
// pueda torcer: nadie aprueba lo que entrego, dos revisores no deciden sobre lo
// mismo, un rechazo explica que corregir, y corregir no borra lo rechazado.

const CLAVE_FLUJO = 'una-contrasena-larga-de-prueba';

beforeEach(function (): void {
    $this->tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Acme',
        slug: 'acme',
        domain: 'acme.ronda.test',
        ownerName: 'Duena de Acme',
        ownerEmail: 'owner@acme.test',
        ownerPassword: CLAVE_FLUJO,
    ));

    Storage::fake(StoreEvidence::DISK);
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();

    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    dropTenantDatabase($tenant);
});

function enAcmeFlujo(Closure $fn): mixed
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;

    return $tenant->run($fn);
}

function ahoraFlujo(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-09-16 20:00:00', 'UTC');
}

function personaConRol(string $email, RoleName $rol, ?Site $sede = null): User
{
    $user = User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => CLAVE_FLUJO]);
    $user->assignRole($rol->value);

    if ($sede instanceof Site) {
        $user->sites()->attach($sede->id);
    }

    return $user->fresh();
}

/**
 * Un arqueo entregado en la sede, por su encargada. Plantilla con monto
 * (obligatorio y reportable), foto opcional y notas.
 */
function arqueoEntregado(?Site $sede = null, string $monto = '100.00', array $evidencia = []): Submission
{
    $sede ??= Site::factory()->create();
    $owner = User::query()->where('email', 'owner@acme.test')->firstOrFail();

    $plantilla = resolve(CreateTemplate::class)(new TemplateData(code: 'ARQ-'.$sede->id, name: 'Arqueo'));
    resolve(PublishTemplateVersion::class)($plantilla, FormSchema::fromArray([
        ['key' => 'monto', 'type' => 'money', 'label' => 'Monto', 'required' => true, 'reportable' => true],
        ['key' => 'foto', 'type' => 'photo', 'label' => 'Foto'],
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
        skipHolidays: false,
        siteIds: [$sede->id],
    ));
    resolve(MaterializeObligations::class)($programacion, '2026-09-16', '2026-09-16');
    $obligacion = Obligation::query()->where('site_id', $sede->id)->firstOrFail();

    $encargada = User::query()->where('email', 'encargada-'.$sede->id.'@acme.test')->first()
        ?? personaConRol('encargada-'.$sede->id.'@acme.test', RoleName::SiteManager, $sede);

    return resolve(SubmitReport::class)($obligacion, $encargada, ['monto' => $monto], ahoraFlujo(), $evidencia);
}

/**
 * @return list<string>
 */
function historialDe(Submission $envio): array
{
    return $envio->transitions()->pluck('to_state')->all();
}

it('aprueba un envio, lo toma de paso y deja el historial completo', function (): void {
    enAcmeFlujo(function (): void {
        Event::fake([SubmissionApproved::class]);

        $envio = arqueoEntregado();
        $supervisora = personaConRol('supervisora@acme.test', RoleName::Supervisor, $envio->site);

        resolve(ApproveSubmission::class)($envio, $supervisora, 'Cuadra con el voucher');

        $envio->refresh();

        expect($envio->state)->toBeInstanceOf(Approved::class)
            ->and($envio->reviewer_id)->toBe($supervisora->id)
            ->and($envio->reviewed_at)->not->toBeNull()
            ->and(historialDe($envio))->toBe([Submitted::$name, UnderReview::$name, Approved::$name])
            ->and($envio->transitions()->get()->last()?->comment)->toBe('Cuadra con el voucher');

        Event::assertDispatched(SubmissionApproved::class, fn (SubmissionApproved $e): bool => $e->submissionId === $envio->id);
    });
})->group('workflow');

it('no deja rechazar sin explicar que corregir, ni reserva el envio al intentarlo', function (): void {
    enAcmeFlujo(function (): void {
        $envio = arqueoEntregado();
        $supervisora = personaConRol('supervisora@acme.test', RoleName::Supervisor, $envio->site);

        expect(fn () => resolve(RejectSubmission::class)($envio, $supervisora, '   '))
            ->toThrow(CannotReview::class);

        expect($envio->refresh()->state)->toBeInstanceOf(Submitted::class)
            ->and($envio->reviewer_id)->toBeNull();
    });
})->group('workflow');

it('nadie revisa un envio que entrego, ni siquiera la propietaria', function (): void {
    // OwnerGate deja pasar a la propietaria por encima de toda Policy. La
    // separacion de funciones no es un permiso: la aplica la Action.
    enAcmeFlujo(function (): void {
        $sede = Site::factory()->create();
        $owner = User::query()->where('email', 'owner@acme.test')->firstOrFail();
        $sede->users()->attach($owner->id);

        $envio = arqueoEntregado($sede);
        $envio->forceFill(['author_id' => $owner->id])->save();

        expect(fn () => resolve(ApproveSubmission::class)($envio, $owner))
            ->toThrow(CannotReview::class, 'entregaste');

        expect($envio->refresh()->state)->toBeInstanceOf(Submitted::class);
    });
})->group('workflow');

it('lo que tomo una revisora no lo decide otra', function (): void {
    enAcmeFlujo(function (): void {
        $envio = arqueoEntregado();
        $ana = personaConRol('ana@acme.test', RoleName::Supervisor, $envio->site);
        $beto = personaConRol('beto@acme.test', RoleName::Supervisor, $envio->site);

        resolve(TakeSubmission::class)($envio, $ana);

        expect(fn () => resolve(ApproveSubmission::class)($envio, $beto))->toThrow(CannotReview::class, 'Ana')
            ->and(fn () => resolve(RejectSubmission::class)($envio, $beto, 'No'))->toThrow(CannotReview::class);

        // Tomarlo otra vez quien ya lo tiene no cambia nada.
        resolve(TakeSubmission::class)($envio, $ana);

        expect($envio->refresh()->reviewer_id)->toBe($ana->id)
            ->and(historialDe($envio))->toBe([Submitted::$name, UnderReview::$name]);
    });
})->group('workflow');

it('no revisa lo ya aprobado', function (): void {
    enAcmeFlujo(function (): void {
        $envio = arqueoEntregado();
        $supervisora = personaConRol('supervisora@acme.test', RoleName::Supervisor, $envio->site);

        resolve(ApproveSubmission::class)($envio, $supervisora);

        expect(fn () => resolve(RejectSubmission::class)($envio, $supervisora, 'Me arrepiento'))
            ->toThrow(CannotReview::class);
    });
})->group('workflow');

it('rechazar no deshace el cumplimiento de la obligacion', function (): void {
    enAcmeFlujo(function (): void {
        Event::fake([SubmissionRejected::class]);

        $envio = arqueoEntregado();
        $supervisora = personaConRol('supervisora@acme.test', RoleName::Supervisor, $envio->site);

        resolve(RejectSubmission::class)($envio, $supervisora, 'Falta la foto del voucher');

        expect($envio->refresh()->state)->toBeInstanceOf(Rejected::class)
            ->and(Obligation::query()->findOrFail($envio->obligation_id)->status)->toBeInstanceOf(Fulfilled::class);

        Event::assertDispatched(SubmissionRejected::class);
    });
})->group('workflow');

it('corregir guarda lo rechazado, reescribe la respuesta y la replica, y vuelve a la bandeja', function (): void {
    enAcmeFlujo(function (): void {
        $envio = arqueoEntregado(monto: '100.00');
        $entregadoEn = $envio->submitted_at->toDateTimeString();
        $supervisora = personaConRol('supervisora@acme.test', RoleName::Supervisor, $envio->site);
        resolve(RejectSubmission::class)($envio, $supervisora, 'El monto no cuadra');

        $autora = User::query()->findOrFail($envio->author_id);
        resolve(CorrectSubmission::class)($envio, $autora, ['monto' => '120.50', 'notas' => 'Recontado']);

        $envio->refresh();
        $revision = SubmissionRevision::query()->sole();

        expect($envio->state)->toBeInstanceOf(Submitted::class)
            ->and($envio->revision)->toBe(2)
            ->and($envio->reviewer_id)->toBeNull()
            ->and($envio->data)->toEqual(['monto' => '120.50', 'notas' => 'Recontado'])
            ->and($envio->values()->where('field_key', 'monto')->value('value_numeric'))->toBe('120.5000')
            // La entrega original manda sobre la puntualidad.
            ->and($envio->submitted_at->toDateTimeString())->toBe($entregadoEn)
            // Lo rechazado sigue ahi, con su motivo.
            ->and($revision->number)->toBe(1)
            ->and($revision->data)->toBe(['monto' => '100.00'])
            ->and($revision->rejection_comment)->toBe('El monto no cuadra')
            ->and(historialDe($envio))->toBe([Submitted::$name, UnderReview::$name, Rejected::$name, Submitted::$name]);
    });
})->group('workflow');

it('al corregir conserva la foto que no se reemplaza y guarda la nueva cuando se reemplaza', function (): void {
    enAcmeFlujo(function (): void {
        $envio = arqueoEntregado(evidencia: ['foto' => [new EvidenceUpload(EvidenceFixtures::jpeg(), 'primera.jpg')]]);
        $primera = (string) Attachment::query()->sole()->id;
        $supervisora = personaConRol('supervisora@acme.test', RoleName::Supervisor, $envio->site);
        $autora = User::query()->findOrFail($envio->author_id);
        $corregir = resolve(CorrectSubmission::class);

        // Primera correccion: solo el monto. La foto se conserva.
        resolve(RejectSubmission::class)($envio, $supervisora, 'Monto');
        $corregir($envio, $autora, ['monto' => '101']);

        expect($envio->refresh()->data['foto'])->toBe([$primera]);

        // Segunda: otra foto. La vigente es la nueva; la anterior sigue guardada.
        resolve(RejectSubmission::class)($envio, $supervisora, 'Foto borrosa');
        $corregir($envio, $autora, ['monto' => '101'], ['foto' => [new EvidenceUpload(EvidenceFixtures::png(), 'segunda.png')]]);

        $envio->refresh();
        $nueva = $envio->data['foto'][0];

        expect($nueva)->not->toBe($primera)
            ->and(Attachment::query()->count())->toBe(2)
            ->and(SubmissionRevision::query()->where('number', 2)->first()?->data)->toEqual(['monto' => '101', 'foto' => [$primera]]);

        // La ficha muestra solo la vigente.
        auth()->login($supervisora);

        Livewire::test(SubmissionDetail::class, ['submission' => $envio])
            ->assertViewHas('attachments', fn ($porCampo): bool => $porCampo->flatten()->pluck('id')->map(strval(...))->all() === [$nueva]);
    });
})->group('workflow');

it('corrige contra la version con la que se entrego, no contra la vigente', function (): void {
    enAcmeFlujo(function (): void {
        $envio = arqueoEntregado();
        $supervisora = personaConRol('supervisora@acme.test', RoleName::Supervisor, $envio->site);
        resolve(RejectSubmission::class)($envio, $supervisora, 'Revisa');

        // Despues de entregar, la plantilla gana un campo obligatorio nuevo.
        $plantilla = Template::query()->findOrFail($envio->template_id);
        resolve(PublishTemplateVersion::class)($plantilla, FormSchema::fromArray([
            ['key' => 'monto', 'type' => 'money', 'label' => 'Monto', 'required' => true],
            ['key' => 'voucher', 'type' => 'text', 'label' => 'Voucher', 'required' => true],
        ]), User::query()->where('email', 'owner@acme.test')->firstOrFail());

        resolve(CorrectSubmission::class)($envio, User::query()->findOrFail($envio->author_id), ['monto' => '99']);

        expect($envio->refresh()->state)->toBeInstanceOf(Submitted::class);
    });
})->group('workflow');

it('una correccion invalida no toca nada', function (): void {
    enAcmeFlujo(function (): void {
        $envio = arqueoEntregado(monto: '100.00');
        $supervisora = personaConRol('supervisora@acme.test', RoleName::Supervisor, $envio->site);
        resolve(RejectSubmission::class)($envio, $supervisora, 'Revisa');

        expect(fn () => resolve(CorrectSubmission::class)($envio, User::query()->findOrFail($envio->author_id), ['monto' => 'cien']))
            ->toThrow(InvalidAnswers::class);

        expect($envio->refresh()->state)->toBeInstanceOf(Rejected::class)
            ->and($envio->data)->toBe(['monto' => '100.00'])
            ->and(SubmissionRevision::query()->count())->toBe(0);
    });
})->group('workflow');

it('solo se corrige lo rechazado', function (): void {
    enAcmeFlujo(function (): void {
        $envio = arqueoEntregado();

        expect(fn () => resolve(CorrectSubmission::class)($envio, User::query()->findOrFail($envio->author_id), ['monto' => '1']))
            ->toThrow(CannotReview::class);
    });
})->group('workflow');

it('revisa desde el panel de la ficha y rechaza con motivo', function (): void {
    enAcmeFlujo(function (): void {
        $envio = arqueoEntregado();
        $supervisora = personaConRol('supervisora@acme.test', RoleName::Supervisor, $envio->site);
        auth()->login($supervisora);

        Livewire::test(ReviewPanel::class, ['submission' => $envio])
            ->assertViewHas('canApprove', true)
            ->call('reject')
            ->assertHasErrors(['decisionComment' => 'required'])
            ->set('decisionComment', 'Falta firmar')
            ->call('reject')
            ->assertHasNoErrors()
            ->assertRedirect(route('submissions.show', $envio));

        expect($envio->refresh()->state)->toBeInstanceOf(Rejected::class);
    });
})->group('workflow');

it('muestra el error del flujo en el panel en vez de reventar', function (): void {
    enAcmeFlujo(function (): void {
        $envio = arqueoEntregado();
        $ana = personaConRol('ana@acme.test', RoleName::Supervisor, $envio->site);
        $beto = personaConRol('beto@acme.test', RoleName::Supervisor, $envio->site);
        auth()->login($beto);

        // Beto abre la ficha; Ana lo toma antes de que Beto decida.
        $panel = Livewire::test(ReviewPanel::class, ['submission' => $envio]);
        resolve(TakeSubmission::class)($envio, $ana);

        $panel->call('approve')->assertHasErrors(['review']);

        expect($envio->refresh()->reviewer_id)->toBe($ana->id);
    });
})->group('workflow');

it('aprobar es un permiso aparte de revisar', function (): void {
    enAcmeFlujo(function (): void {
        $envio = arqueoEntregado();

        $revisor = User::create(['name' => 'Revisor', 'email' => 'revisor@acme.test', 'password' => CLAVE_FLUJO]);
        $revisor->givePermissionTo([
            PermissionName::SiteView->value,
            PermissionName::SubmissionView->value,
            PermissionName::SubmissionReview->value,
        ]);
        $revisor->sites()->attach($envio->site_id);
        auth()->login($revisor->fresh());

        Livewire::test(ReviewPanel::class, ['submission' => $envio])
            ->assertViewHas('canApprove', false)
            ->assertViewHas('canReject', true)
            ->call('approve')
            ->assertForbidden();

        expect($envio->refresh()->state)->toBeInstanceOf(Submitted::class);
    });
})->group('workflow');

it('una supervisora no revisa ni ve en su bandeja envios de sedes que no alcanza', function (): void {
    enAcmeFlujo(function (): void {
        $mia = Site::factory()->create();
        $ajena = Site::factory()->create();
        $envioMio = arqueoEntregado($mia);
        $envioAjeno = arqueoEntregado($ajena);

        auth()->login(personaConRol('supervisora@acme.test', RoleName::Supervisor, $mia));

        Livewire::test(ReviewInbox::class)
            ->assertViewHas('submissions', fn ($pagina): bool => collect($pagina->items())->pluck('id')->all() === [$envioMio->id]);

        Livewire::test(ReviewPanel::class, ['submission' => $envioAjeno])->assertForbidden();
    });
})->group('workflow');

it('la bandeja no es para quien solo entrega', function (): void {
    enAcmeFlujo(function (): void {
        $sede = Site::factory()->create();
        auth()->login(personaConRol('encargada@acme.test', RoleName::SiteManager, $sede));

        Livewire::test(ReviewInbox::class)->assertForbidden();
    });
})->group('workflow');

it('corrige desde la pantalla con la respuesta rechazada ya puesta', function (): void {
    enAcmeFlujo(function (): void {
        $envio = arqueoEntregado(monto: '100.00');
        resolve(RejectSubmission::class)($envio, personaConRol('supervisora@acme.test', RoleName::Supervisor, $envio->site), 'Adjunta foto');

        $autora = User::query()->findOrFail($envio->author_id);
        auth()->login($autora);
        CarbonImmutable::setTestNow(ahoraFlujo());

        Livewire::test(SubmissionCorrectionForm::class, ['submission' => $envio])
            ->assertSet('answers.monto', '100.00')
            ->assertViewHas('rejectionComment', 'Adjunta foto')
            ->set('uploads.foto', [UploadedFile::fake()->createWithContent('foto.jpg', EvidenceFixtures::jpeg())])
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('submissions.show', $envio));

        $envio->refresh();

        expect($envio->state)->toBeInstanceOf(Submitted::class)
            ->and($envio->data['foto'])->toHaveCount(1);
    });
})->group('workflow');

it('sirve la bandeja, la ficha con su panel y la correccion dentro del layout', function (): void {
    $ids = enAcmeFlujo(function (): array {
        $envio = arqueoEntregado();
        $supervisora = personaConRol('supervisora@acme.test', RoleName::Supervisor, $envio->site);
        $rechazado = arqueoEntregado();
        resolve(RejectSubmission::class)($rechazado, $supervisora, 'Revisa');

        return [$envio->id, $rechazado->id, $rechazado->author_id];
    });

    $url = 'http://acme.ronda.test';

    $this->post($url.'/login', ['email' => 'supervisora@acme.test', 'password' => CLAVE_FLUJO]);

    $this->get($url.'/revision')->assertOk()->assertSee('Arqueo')->assertSee($url.'/revision');
    $this->get($url.'/envios/'.$ids[0])->assertOk()->assertSee('Historial')->assertSee('Aprobar');

    $this->post($url.'/logout');
    $email = enAcmeFlujo(fn (): string => User::query()->findOrFail($ids[2])->email);
    $this->post($url.'/login', ['email' => $email, 'password' => CLAVE_FLUJO]);

    $this->get($url.'/pendientes')->assertOk()->assertSee('Rechazados, por corregir')
        // El menu no le ofrece la bandeja a quien solo entrega.
        ->assertDontSee($url.'/revision');
    $this->get($url.'/envios/'.$ids[1].'/corregir')->assertOk()->assertSee('Revisa');
})->group('workflow');
