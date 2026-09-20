<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Forms\Application\Actions\CreateTemplate;
use Ronda\Forms\Application\Actions\PublishTemplateVersion;
use Ronda\Forms\Application\Data\TemplateData;
use Ronda\Forms\Domain\ValueObjects\FormSchema;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\RoleName;
use Ronda\Notifications\Application\Actions\EscalateMissedObligations;
use Ronda\Notifications\Application\Actions\EscalateStaleReviews;
use Ronda\Notifications\Application\Actions\SendObligationReminders;
use Ronda\Notifications\Domain\Models\SlaEvent;
use Ronda\Notifications\Domain\NotificationTopic;
use Ronda\Notifications\Infrastructure\Notifications\OperationalNotification;
use Ronda\Notifications\Presentation\Livewire\NotificationBell;
use Ronda\Notifications\Presentation\Livewire\NotificationList;
use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Models\Tenant;
use Ronda\Scheduling\Application\Actions\CreateSchedule;
use Ronda\Scheduling\Application\Actions\MarkMissedObligations;
use Ronda\Scheduling\Application\Actions\MaterializeObligations;
use Ronda\Scheduling\Application\Data\ScheduleData;
use Ronda\Scheduling\Domain\Models\Obligation;
use Ronda\Scheduling\Domain\ScheduleScope;
use Ronda\Submissions\Application\Actions\SubmitReport;
use Ronda\Workflow\Application\Actions\CorrectSubmission;
use Ronda\Workflow\Application\Actions\RejectSubmission;

// Avisos y escalamiento. RONDA-PLAN-MAESTRO.md sec. 9.3 y 9.4
//
// Lo que se prueba: que el aviso llega a quien puede hacer algo con el, que no
// se repite cada hora, y que sube por la escalera cuando nadie reacciona.

const CLAVE_AVISOS = 'una-contrasena-larga-de-prueba';

beforeEach(function (): void {
    $this->tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Acme',
        slug: 'acme',
        domain: 'acme.ronda.test',
        ownerName: 'Duena de Acme',
        ownerEmail: 'owner@acme.test',
        ownerPassword: CLAVE_AVISOS,
    ));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();

    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    dropTenantDatabase($tenant);
});

function enAcmeAvisos(Closure $fn): mixed
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;

    return $tenant->run($fn);
}

function personaDeAviso(string $email, RoleName $rol, ?Site $sede = null): User
{
    $user = User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => CLAVE_AVISOS]);
    $user->assignRole($rol->value);

    if ($sede instanceof Site) {
        $user->sites()->attach($sede->id);
    }

    return $user->fresh();
}

/**
 * Obligacion del 17 de septiembre, ventana 08:00-18:00 de Lima
 * (13:00-23:00 UTC), en una sede con encargada.
 */
function obligacionDelDia(?Site $sede = null, string $abre = '08:00', string $cierra = '18:00'): Obligation
{
    $sede ??= Site::factory()->create();
    $owner = User::query()->where('email', 'owner@acme.test')->firstOrFail();

    $plantilla = resolve(CreateTemplate::class)(new TemplateData(code: 'ARQ-'.$sede->id, name: 'Arqueo'));
    resolve(PublishTemplateVersion::class)($plantilla, FormSchema::fromArray([
        ['key' => 'monto', 'type' => 'money', 'label' => 'Monto', 'required' => true],
    ]), $owner);

    $programacion = resolve(CreateSchedule::class)(new ScheduleData(
        templateId: $plantilla->id,
        name: 'Arqueo diario',
        scope: ScheduleScope::Sites,
        rrule: 'FREQ=DAILY',
        windowStart: $abre,
        windowEnd: $cierra,
        startsOn: '2026-09-01',
        skipHolidays: false,
        siteIds: [$sede->id],
    ));

    resolve(MaterializeObligations::class)($programacion, '2026-09-17', '2026-09-17');

    return Obligation::query()->where('site_id', $sede->id)->firstOrFail();
}

it('recuerda una entrega que vence pronto, una sola vez', function (): void {
    enAcmeAvisos(function (): void {
        Notification::fake();

        $obligacion = obligacionDelDia();
        $encargada = personaDeAviso('encargada@acme.test', RoleName::SiteManager, $obligacion->site);

        // 21:30 UTC: la ventana abrio a las 13:00 y vence a las 23:00.
        $ahora = CarbonImmutable::parse('2026-09-17 21:30', 'UTC');
        $recordar = resolve(SendObligationReminders::class);

        expect($recordar($ahora))->toBe(1)
            // La segunda pasada del job no vuelve a avisar.
            ->and($recordar($ahora->addMinutes(30)))->toBe(0);

        Notification::assertSentToTimes($encargada, OperationalNotification::class, 1);
        Notification::assertSentTo($encargada, OperationalNotification::class, function (OperationalNotification $aviso) use ($encargada): bool {
            $datos = $aviso->toArray($encargada);

            return $datos['topic'] === NotificationTopic::ObligationDueSoon->value
                // La direccion lleva el dominio del cliente, no el central.
                && str_starts_with((string) $datos['url'], 'http://acme.ronda.test/')
                && in_array('mail', $aviso->via($encargada), true);
        });

        expect(SlaEvent::query()->count())->toBe(1);
    });
})->group('notifications');

it('no recuerda una entrega que vence lejos', function (): void {
    enAcmeAvisos(function (): void {
        Notification::fake();

        $obligacion = obligacionDelDia();
        personaDeAviso('encargada@acme.test', RoleName::SiteManager, $obligacion->site);

        // 14:00 UTC: la ventana ya abrio, pero vence a las 23:00; faltan mas de
        // las dos horas del recordatorio.
        expect(resolve(SendObligationReminders::class)(CarbonImmutable::parse('2026-09-17 14:00', 'UTC')))->toBe(0);

        Notification::assertNothingSent();
    });
})->group('notifications');

it('no recuerda lo que todavia no se puede entregar', function (): void {
    // Recordar algo que no se puede hacer es ruido, y el ruido se ignora.
    enAcmeAvisos(function (): void {
        Notification::fake();

        // Ventana corta: 08:00-09:00 de Lima, o sea 13:00-14:00 UTC.
        $obligacion = obligacionDelDia(abre: '08:00', cierra: '09:00');
        personaDeAviso('encargada@acme.test', RoleName::SiteManager, $obligacion->site);

        $recordar = resolve(SendObligationReminders::class);

        // 12:15 UTC: vence dentro de la ventana del recordatorio, pero la
        // entrega todavia no ha abierto.
        expect($recordar(CarbonImmutable::parse('2026-09-17 12:15', 'UTC')))->toBe(0);
        Notification::assertNothingSent();

        // 13:05 UTC: ya abierta.
        expect($recordar(CarbonImmutable::parse('2026-09-17 13:05', 'UTC')))->toBe(1);
    });
})->group('notifications');

it('avisa del incumplimiento a la sede y lo escala a quien revisa y a quien administra', function (): void {
    enAcmeAvisos(function (): void {
        Notification::fake();

        $obligacion = obligacionDelDia();
        $encargada = personaDeAviso('encargada@acme.test', RoleName::SiteManager, $obligacion->site);
        $supervisora = personaDeAviso('supervisora@acme.test', RoleName::Supervisor, $obligacion->site);
        $owner = User::query()->where('email', 'owner@acme.test')->firstOrFail();

        // Pasado el cierre, el motor la marca incumplida.
        $cierre = CarbonImmutable::parse($obligacion->closes_at);
        resolve(MarkMissedObligations::class)($cierre->addMinute());

        $escalar = resolve(EscalateMissedObligations::class);

        // Al momento: la sede.
        expect($escalar($cierre->addMinutes(5)))->toBe(1);
        Notification::assertSentToTimes($encargada, OperationalNotification::class, 1);
        Notification::assertNotSentTo($supervisora, OperationalNotification::class);

        // A las dos horas: quien revisa. La sede no repite.
        expect($escalar($cierre->addHours(3)))->toBe(1);
        Notification::assertSentToTimes($supervisora, OperationalNotification::class, 1);
        Notification::assertSentToTimes($encargada, OperationalNotification::class, 1);

        // Al dia siguiente: quien administra el cliente.
        expect($escalar($cierre->addHours(26)))->toBe(1);
        Notification::assertSentToTimes($owner, OperationalNotification::class, 1);

        // Y ya no hay mas peldanos.
        expect($escalar($cierre->addDays(3)))->toBe(0)
            ->and(SlaEvent::query()->where('topic', NotificationTopic::ObligationMissed->value)->pluck('level')->all())
            ->toBe([0, 1, 2]);
    });
})->group('notifications');

it('no avisa a quien no alcanza la sede', function (): void {
    enAcmeAvisos(function (): void {
        Notification::fake();

        $obligacion = obligacionDelDia();
        personaDeAviso('encargada@acme.test', RoleName::SiteManager, $obligacion->site);
        $ajena = personaDeAviso('otra@acme.test', RoleName::SiteManager, Site::factory()->create());

        resolve(SendObligationReminders::class)(CarbonImmutable::parse('2026-09-17 21:30', 'UTC'));

        Notification::assertNotSentTo($ajena, OperationalNotification::class);
    });
})->group('notifications');

it('avisa a quien revisa cuando llega un envio, y no al que lo entrego', function (): void {
    enAcmeAvisos(function (): void {
        Notification::fake();

        $obligacion = obligacionDelDia();
        $encargada = personaDeAviso('encargada@acme.test', RoleName::SiteManager, $obligacion->site);
        $supervisora = personaDeAviso('supervisora@acme.test', RoleName::Supervisor, $obligacion->site);

        resolve(SubmitReport::class)($obligacion, $encargada, ['monto' => '10'], CarbonImmutable::parse('2026-09-17 20:00', 'UTC'));

        Notification::assertSentToTimes($supervisora, OperationalNotification::class, 1);
        Notification::assertNotSentTo($encargada, OperationalNotification::class);
    });
})->group('notifications');

it('avisa a quien administra cuando la sede no tiene mas revisor que el propio autor', function (): void {
    // El caso de la empresa chica, que es con la que se empieza: una sede, una
    // encargada y la duena. La encargada entrega y no puede revisarse a si
    // misma; si el aviso se quedara en las personas asignadas a la sede, el
    // reporte esperaria revision sin que se enterara nadie.
    enAcmeAvisos(function (): void {
        Notification::fake();

        $obligacion = obligacionDelDia();
        $encargada = personaDeAviso('encargada@acme.test', RoleName::SiteManager, $obligacion->site);
        $duena = User::query()->where('email', 'owner@acme.test')->firstOrFail();

        // La duena NO esta asignada a la sede, pero puede revisar el parque.
        expect($duena->sites()->count())->toBe(0);

        resolve(SubmitReport::class)($obligacion, $encargada, ['monto' => '10'], CarbonImmutable::parse('2026-09-17 20:00', 'UTC'));

        Notification::assertSentToTimes($duena, OperationalNotification::class, 1);
        Notification::assertNotSentTo($encargada, OperationalNotification::class);
    });
})->group('notifications');

it('no molesta a quien administra cuando la sede si tiene revisor', function (): void {
    // El respaldo es solo eso: con una supervisora asignada, la duena no
    // recibe nada. Un cliente grande no puede acabar con todos los avisos de
    // todas sus sedes en el correo de gerencia.
    enAcmeAvisos(function (): void {
        Notification::fake();

        $obligacion = obligacionDelDia();
        $encargada = personaDeAviso('encargada@acme.test', RoleName::SiteManager, $obligacion->site);
        personaDeAviso('supervisora@acme.test', RoleName::Supervisor, $obligacion->site);
        $duena = User::query()->where('email', 'owner@acme.test')->firstOrFail();

        resolve(SubmitReport::class)($obligacion, $encargada, ['monto' => '10'], CarbonImmutable::parse('2026-09-17 20:00', 'UTC'));

        Notification::assertNotSentTo($duena, OperationalNotification::class);
    });
})->group('notifications');

it('le dice a quien entrego que su reporte fue rechazado, con el motivo', function (): void {
    enAcmeAvisos(function (): void {
        $obligacion = obligacionDelDia();
        $encargada = personaDeAviso('encargada@acme.test', RoleName::SiteManager, $obligacion->site);
        $supervisora = personaDeAviso('supervisora@acme.test', RoleName::Supervisor, $obligacion->site);

        $envio = resolve(SubmitReport::class)($obligacion, $encargada, ['monto' => '10'], CarbonImmutable::parse('2026-09-17 20:00', 'UTC'));

        Notification::fake();

        resolve(RejectSubmission::class)($envio, $supervisora, 'Falta el voucher del deposito');

        Notification::assertSentTo($encargada, OperationalNotification::class, function (OperationalNotification $aviso) use ($encargada): bool {
            $datos = $aviso->toArray($encargada);

            return $datos['topic'] === NotificationTopic::SubmissionRejected->value
                && str_contains((string) $datos['body'], 'Falta el voucher del deposito')
                && in_array('mail', $aviso->via($encargada), true);
        });
    });
})->group('notifications');

it('reclama un envio que lleva demasiado esperando revision, y vuelve a hacerlo tras una correccion', function (): void {
    enAcmeAvisos(function (): void {
        $obligacion = obligacionDelDia();
        $encargada = personaDeAviso('encargada@acme.test', RoleName::SiteManager, $obligacion->site);
        $supervisora = personaDeAviso('supervisora@acme.test', RoleName::Supervisor, $obligacion->site);
        $entregadoEn = CarbonImmutable::parse('2026-09-17 20:00', 'UTC');
        $envio = resolve(SubmitReport::class)($obligacion, $encargada, ['monto' => '10'], $entregadoEn);

        Notification::fake();
        $reclamar = resolve(EscalateStaleReviews::class);

        // La espera se cuenta desde que entro en la bandeja, que es la marca
        // del propio envio y no la hora de entrega que se paso a la Action.
        $enBandeja = CarbonImmutable::parse($envio->refresh()->updated_at);

        // El primer peldano es a las 24 horas.
        expect($reclamar($enBandeja->addHours(10)))->toBe(0)
            ->and($reclamar($enBandeja->addHours(25)))->toBe(1)
            ->and($reclamar($enBandeja->addHours(26)))->toBe(0);

        Notification::assertSentToTimes($supervisora, OperationalNotification::class, 1);

        // Rechazado y corregido: la espera de la revision empieza de cero, y el
        // aviso puede repetirse para la nueva revision.
        resolve(RejectSubmission::class)($envio, $supervisora, 'Revisa el monto');
        resolve(CorrectSubmission::class)($envio->refresh(), $encargada, ['monto' => '12']);

        expect($reclamar(CarbonImmutable::parse($envio->refresh()->updated_at)->addHours(25)))->toBe(1)
            ->and(SlaEvent::query()->where('topic', NotificationTopic::ReviewOverdue->value)->count())->toBe(2);
    });
})->group('notifications');

it('guarda el aviso en la campana y deja marcarlo como leido', function (): void {
    enAcmeAvisos(function (): void {
        $obligacion = obligacionDelDia();
        $encargada = personaDeAviso('encargada@acme.test', RoleName::SiteManager, $obligacion->site);

        resolve(SendObligationReminders::class)(CarbonImmutable::parse('2026-09-17 21:30', 'UTC'));

        auth()->login($encargada);

        Livewire::test(NotificationBell::class)
            ->assertViewHas('unread', 1)
            ->assertSee('Arqueo');

        $aviso = $encargada->unreadNotifications()->firstOrFail();

        Livewire::test(NotificationList::class)
            ->assertViewHas('unread', 1)
            ->call('markAsRead', $aviso->id)
            ->assertViewHas('unread', 0);

        expect($encargada->refresh()->unreadNotifications()->count())->toBe(0)
            ->and($encargada->notifications()->count())->toBe(1);
    });
})->group('notifications');

it('sirve la pantalla de notificaciones dentro del layout', function (): void {
    enAcmeAvisos(function (): void {
        $obligacion = obligacionDelDia();
        personaDeAviso('encargada@acme.test', RoleName::SiteManager, $obligacion->site);
        resolve(SendObligationReminders::class)(CarbonImmutable::parse('2026-09-17 21:30', 'UTC'));
    });

    $url = 'http://acme.ronda.test';
    $this->post($url.'/login', ['email' => 'encargada@acme.test', 'password' => CLAVE_AVISOS]);

    $this->get($url.'/notificaciones')
        ->assertOk()
        ->assertSee('Notificaciones')
        ->assertSee('Arqueo');
})->group('notifications');

it('lanza el repaso en todos los tenants desde el comando', function (): void {
    enAcmeAvisos(function (): void {
        $obligacion = obligacionDelDia();
        personaDeAviso('encargada@acme.test', RoleName::SiteManager, $obligacion->site);
    });

    Notification::fake();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-17 21:30', 'UTC'));

    $this->artisan('notifications:sla --sync')->assertSuccessful();

    enAcmeAvisos(function (): void {
        expect(SlaEvent::query()->where('topic', NotificationTopic::ObligationDueSoon->value)->count())->toBe(1);
    });
})->group('notifications');
