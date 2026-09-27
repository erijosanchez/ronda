<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Ronda\Api\Application\Actions\QueueWebhook;
use Ronda\Api\Application\Actions\RegisterWebhookEndpoint;
use Ronda\Api\Application\Actions\ResendWebhookDelivery;
use Ronda\Api\Domain\Exceptions\UnsafeWebhookUrl;
use Ronda\Api\Domain\Exceptions\WebhookUrlRejection;
use Ronda\Api\Domain\Models\WebhookDelivery;
use Ronda\Api\Domain\Models\WebhookEndpoint;
use Ronda\Api\Domain\Webhooks\WebhookEvent;
use Ronda\Api\Domain\Webhooks\WebhookSender;
use Ronda\Api\Infrastructure\Webhooks\HttpWebhookSender;
use Ronda\Api\Presentation\Livewire\Webhooks;
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
use Ronda\Scheduling\Application\Actions\MarkMissedObligations;
use Ronda\Scheduling\Application\Actions\MaterializeObligations;
use Ronda\Scheduling\Application\Data\ScheduleData;
use Ronda\Scheduling\Domain\Models\Obligation;
use Ronda\Scheduling\Domain\ScheduleScope;
use Ronda\Submissions\Application\Actions\SubmitReport;

// Webhooks salientes. RONDA-PLAN-MAESTRO.md sec. 13.2
//
// Lo que se prueba aqui no es «sale una peticion HTTP». Eso es lo facil. Es:
//
//   1. Que va FIRMADA y que la firma se puede verificar del otro lado.
//   2. Que no se puede usar Ronda para llamar a su propia red (SSRF), ni
//      escribiendo la IP, ni escondiendola detras de un nombre, ni con una
//      redireccion.
//   3. Que un destino caido se reintenta con espera creciente, se apaga solo y
//      queda un registro consultable con reenvio a mano.

const CLAVE_WEBHOOKS = 'una-contrasena-larga-de-prueba';

/** Un `.test` no resuelve: la prueba no depende del DNS de quien la corre. */
const URL_DESTINO = 'https://erp.ejemplo.test/ronda/avisos';

beforeEach(function (): void {
    $this->seed(PlanSeeder::class);

    $this->tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Acme',
        slug: 'acme',
        domain: 'acme.ronda.test',
        ownerName: 'Duena de Acme',
        ownerEmail: 'owner@acme.test',
        ownerPassword: CLAVE_WEBHOOKS,
    ));

    // Los webhooks son del plan, como la API (sec. 3.6).
    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    resolve(ChangeTenantPlan::class)($tenant, PlanCode::Pro);
    app()->forgetScopedInstances();

    // Por defecto, como en produccion: solo https y nada de redes privadas.
    config()->set('webhooks.allow_insecure', false);
});

afterEach(function (): void {
    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    dropTenantDatabase($tenant);
});

function enAcmeWebhooks(Closure $fn): mixed
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;

    return $tenant->run($fn);
}

/**
 * @param  list<WebhookEvent>  $eventos
 */
function destinoDeAcme(array $eventos = [WebhookEvent::SubmissionSubmitted], string $url = URL_DESTINO): WebhookEndpoint
{
    return resolve(RegisterWebhookEndpoint::class)($url, $eventos, 'ERP de prueba');
}

function duenaDeAcme(): User
{
    $duena = User::query()->where('email', 'owner@acme.test')->firstOrFail();
    auth()->login($duena);

    return $duena;
}

it('firma cada aviso con el secreto del destino y la marca de tiempo', function (): void {
    Http::fake([URL_DESTINO => Http::response('', 200)]);

    enAcmeWebhooks(function (): void {
        $destino = destinoDeAcme();

        resolve(QueueWebhook::class)(WebhookEvent::SubmissionSubmitted, ['id' => 7]);

        Http::assertSent(function ($peticion) use ($destino): bool {
            $marca = $peticion->header('X-Ronda-Timestamp')[0];
            $firma = $peticion->header('X-Ronda-Signature')[0];

            // Exactamente lo que se le dice al cliente que haga en su lado: si
            // esta cuenta cambia, la documentacion de la pantalla miente.
            $esperada = hash_hmac('sha256', $marca.'.'.$peticion->body(), $destino->secret);

            return hash_equals($esperada, $firma)
                && $peticion->header('X-Ronda-Event')[0] === 'submission.submitted';
        });

        $entrega = WebhookDelivery::query()->firstOrFail();

        expect($entrega->isDelivered())->toBeTrue()
            ->and($entrega->attempts)->toBe(1)
            ->and($entrega->response_status)->toBe(200)
            // El cuerpo guardado es el que se mando: es lo que se reenvia.
            ->and($entrega->payload['data'])->toBe(['id' => 7]);
    });
})->group('webhooks');

it('la firma cambia si cambia el cuerpo', function (): void {
    // Una firma que no dependiera del cuerpo no serviria de nada: quien
    // recibiera un aviso manipulado por el camino no lo notaria.
    enAcmeWebhooks(function (): void {
        $enviar = resolve(WebhookSender::class);

        $uno = $enviar->sign('1700000000', '{"a":1}', 'secreto');
        $otro = $enviar->sign('1700000000', '{"a":2}', 'secreto');
        $tarde = $enviar->sign('1700000001', '{"a":1}', 'secreto');

        expect($uno)->not->toBe($otro)
            // Y tampoco vale reenviar el mismo cuerpo con otra marca.
            ->and($uno)->not->toBe($tarde);
    });
})->group('webhooks');

it('no acepta direcciones que apunten a nuestra propia red', function (string $url, WebhookUrlRejection $motivo): void {
    enAcmeWebhooks(function () use ($url, $motivo): void {
        try {
            destinoDeAcme(url: $url);
            $this->fail("Se acepto «{$url}», que no deberia aceptarse.");
        } catch (UnsafeWebhookUrl $e) {
            expect($e->reason)->toBe($motivo);
        }

        expect(WebhookEndpoint::query()->count())->toBe(0);
    });
})->with([
    'http a secas' => ['http://erp.ejemplo.test/avisos', WebhookUrlRejection::InsecureScheme],
    'nombre interno sin punto' => ['https://postgres/avisos', WebhookUrlRejection::PrivateHost],
    'bucle local' => ['https://127.0.0.1/avisos', WebhookUrlRejection::PrivateHost],
    'metadatos de la nube' => ['https://169.254.169.254/latest/meta-data/', WebhookUrlRejection::PrivateHost],
    'red privada' => ['https://10.0.0.5/avisos', WebhookUrlRejection::PrivateHost],
    'no es una direccion' => ['hola que tal', WebhookUrlRejection::Malformed],
])->group('webhooks');

it('una redireccion no cuenta como entrega y no se sigue', function (): void {
    // La puerta trasera del SSRF: una URL publica y limpia que responde 302
    // hacia 127.0.0.1. Si se siguiera, toda la validacion anterior sobraria.
    Http::fake([
        URL_DESTINO => Http::response('', 302, ['Location' => 'http://127.0.0.1/interno']),
        '*' => Http::response('', 200),
    ]);

    enAcmeWebhooks(function (): void {
        destinoDeAcme();

        resolve(QueueWebhook::class)(WebhookEvent::SubmissionSubmitted, ['id' => 1]);

        $entrega = WebhookDelivery::query()->firstOrFail();

        expect($entrega->isDelivered())->toBeFalse()
            ->and($entrega->response_status)->toBe(302);

        // Una sola peticion: no hubo segundo salto hacia dentro.
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($peticion): bool => str_contains($peticion->url(), '127.0.0.1'));

        // Que no se siguiera no basta con verlo aqui: el doble de Http
        // tampoco sigue redirecciones, asi que quitar `withoutRedirecting()`
        // no rompería esta prueba y el agujero volveria en silencio. Se
        // comprueba sobre el codigo del emisor, que es donde vive la garantia.
        $fuente = (string) file_get_contents((string) new ReflectionClass(HttpWebhookSender::class)->getFileName());

        expect($fuente)->toContain('withoutRedirecting()');
    });
})->group('webhooks');

it('reintenta con espera creciente y se rinde tras seis intentos', function (): void {
    Http::fake([URL_DESTINO => Http::response('vaya', 500)]);

    // Con el reloj quieto: lo que se comprueba es la distancia entre intentos,
    // no cuanto tarda la prueba en correr.
    $ahora = CarbonImmutable::parse('2026-03-01 08:00:00', 'UTC');
    $this->travelTo($ahora);

    enAcmeWebhooks(function () use ($ahora): void {
        destinoDeAcme();

        resolve(QueueWebhook::class)(WebhookEvent::SubmissionSubmitted, ['id' => 1]);

        $entrega = WebhookDelivery::query()->firstOrFail();

        // Primer fallo: sigue pendiente y vuelve a intentarlo en un minuto.
        expect($entrega->status)->toBe(WebhookDelivery::PENDING)
            ->and($entrega->attempts)->toBe(1)
            ->and($entrega->error)->toBe('HTTP 500')
            ->and($entrega->next_attempt_at?->toIso8601String())
            ->toBe($ahora->addMinute()->toIso8601String());

        $enviar = resolve(WebhookSender::class);
        $esperas = [];

        // Los cinco intentos que faltan, cada uno cuando le toca.
        for ($i = 2; $i <= WebhookDelivery::MAX_ATTEMPTS; $i++) {
            $cuando = $entrega->next_attempt_at ?? throw new RuntimeException('Se quedo sin siguiente intento antes de tiempo.');

            $enviar($entrega, $cuando);
            $entrega->refresh();

            $esperas[] = $entrega->next_attempt_at === null
                ? null
                : (int) $cuando->diffInSeconds($entrega->next_attempt_at);
        }

        // La espera se dobla cada vez: 2, 4, 8, 16 minutos... y al sexto
        // intento ya no hay siguiente.
        expect($esperas)->toBe([120, 240, 480, 960, null])
            ->and($entrega->hasFailed())->toBeTrue()
            ->and($entrega->attempts)->toBe(WebhookDelivery::MAX_ATTEMPTS)
            ->and($entrega->next_attempt_at)->toBeNull();
    });
})->group('webhooks');

it('apaga el destino tras demasiados fallos seguidos', function (): void {
    Http::fake([URL_DESTINO => Http::response('', 500)]);

    enAcmeWebhooks(function (): void {
        $destino = destinoDeAcme();

        // A un fallo de rendirse: un servidor muerto no puede tener a Ronda
        // llamandole para siempre.
        $destino->forceFill(['consecutive_failures' => WebhookEndpoint::FAILURE_LIMIT - 1])->save();

        resolve(QueueWebhook::class)(WebhookEvent::SubmissionSubmitted, ['id' => 1]);

        $destino->refresh();

        expect($destino->exhausted())->toBeTrue()
            ->and($destino->is_active)->toBeFalse();

        // Y ya no se le manda nada mas.
        Http::fake([URL_DESTINO => Http::response('', 200)]);
        $encolados = resolve(QueueWebhook::class)(WebhookEvent::SubmissionSubmitted, ['id' => 2]);

        expect($encolados)->toBe(0)
            ->and(WebhookDelivery::query()->count())->toBe(1);
    });
})->group('webhooks');

it('una entrega correcta perdona los fallos anteriores', function (): void {
    Http::fake([URL_DESTINO => Http::response('', 200)]);

    enAcmeWebhooks(function (): void {
        $destino = destinoDeAcme();
        $destino->forceFill(['consecutive_failures' => 9])->save();

        resolve(QueueWebhook::class)(WebhookEvent::SubmissionSubmitted, ['id' => 1]);

        expect($destino->fresh()?->consecutive_failures)->toBe(0);
    });
})->group('webhooks');

it('solo llama a los destinos suscritos y encendidos', function (): void {
    Http::fake(['*' => Http::response('', 200)]);

    enAcmeWebhooks(function (): void {
        $suscrito = destinoDeAcme([WebhookEvent::SubmissionApproved], 'https://uno.ejemplo.test/avisos');
        destinoDeAcme([WebhookEvent::SubmissionSubmitted], 'https://dos.ejemplo.test/avisos');
        $apagado = destinoDeAcme([WebhookEvent::SubmissionApproved], 'https://tres.ejemplo.test/avisos');
        $apagado->forceFill(['is_active' => false])->save();

        $encolados = resolve(QueueWebhook::class)(WebhookEvent::SubmissionApproved, ['id' => 1]);

        expect($encolados)->toBe(1)
            ->and(WebhookDelivery::query()->pluck('webhook_endpoint_id')->all())
            ->toBe([$suscrito->id]);

        // Y al apagado no se le anota una entrega que nadie va a mandar.
        Http::assertSentCount(1);
    });
})->group('webhooks');

it('un plan sin API no manda avisos, aunque queden destinos registrados', function (): void {
    Http::fake(['*' => Http::response('', 200)]);

    // El caso que se escapa si la puerta estuviera solo en la pantalla: el
    // cliente registro sus destinos con Pro y despues bajo de plan.
    enAcmeWebhooks(function (): void {
        destinoDeAcme();
    });

    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    resolve(ChangeTenantPlan::class)($tenant, PlanCode::Starter);
    app()->forgetScopedInstances();

    enAcmeWebhooks(function (): void {
        $encolados = resolve(QueueWebhook::class)(WebhookEvent::SubmissionSubmitted, ['id' => 1]);

        expect($encolados)->toBe(0)
            ->and(WebhookDelivery::query()->count())->toBe(0);

        Http::assertNothingSent();
    });
})->group('webhooks');

it('reenvia a mano el mismo cuerpo que se guardo', function (): void {
    // El caso real: el servidor del cliente estuvo caido una tarde y quiere
    // recuperar esos avisos sin provocar los eventos otra vez.
    // Una secuencia, no dos `fake()`: el segundo no sustituye al primero, y el
    // primer molde que encaja es el que responde.
    Http::fake([URL_DESTINO => Http::sequence()
        ->push('', 500)
        ->push('', 200),
    ]);

    enAcmeWebhooks(function (): void {
        destinoDeAcme();
        resolve(QueueWebhook::class)(WebhookEvent::SubmissionSubmitted, ['id' => 42]);

        $entrega = WebhookDelivery::query()->firstOrFail();
        $cuerpo = $entrega->payload;

        expect($entrega->isDelivered())->toBeFalse();

        resolve(ResendWebhookDelivery::class)($entrega);

        $entrega->refresh();

        expect($entrega->isDelivered())->toBeTrue()
            ->and($entrega->error)->toBeNull()
            ->and($entrega->payload)->toBe($cuerpo);

        Http::assertSent(fn ($peticion): bool => $peticion->data()['data']['id'] === 42);
    });
})->group('webhooks');

it('avisa cuando alguien entrega un reporte', function (): void {
    Http::fake([URL_DESTINO => Http::response('', 200)]);

    enAcmeWebhooks(function (): void {
        destinoDeAcme([WebhookEvent::SubmissionSubmitted]);

        $duena = duenaDeAcme();
        $sede = Site::factory()->create();

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

        $obligacion = Obligation::query()->where('site_id', $sede->id)->firstOrFail();

        $envio = resolve(SubmitReport::class)($obligacion, $duena, ['monto' => '1520.40']);

        $entrega = WebhookDelivery::query()->where('event', 'submission.submitted')->firstOrFail();

        expect($entrega->payload['data']['id'])->toBe($envio->id)
            ->and($entrega->payload['data']['site_id'])->toBe($sede->id)
            ->and($entrega->isDelivered())->toBeTrue();
    });
})->group('webhooks');

it('avisa cuando una obligacion cierra sin entrega', function (): void {
    Http::fake([URL_DESTINO => Http::response('', 200)]);

    enAcmeWebhooks(function (): void {
        destinoDeAcme([WebhookEvent::ObligationMissed]);

        $duena = duenaDeAcme();
        $sede = Site::factory()->create();

        $plantilla = resolve(CreateTemplate::class)(new TemplateData(code: 'RONDA', name: 'Ronda'));
        resolve(PublishTemplateVersion::class)($plantilla, FormSchema::fromArray([
            ['key' => 'nota', 'type' => 'text', 'label' => 'Nota', 'required' => false, 'reportable' => false],
        ]), $duena);

        $programacion = resolve(CreateSchedule::class)(new ScheduleData(
            templateId: $plantilla->id,
            name: 'Ronda diaria',
            scope: ScheduleScope::Sites,
            rrule: 'FREQ=DAILY',
            windowStart: '00:00',
            windowEnd: '23:59',
            startsOn: CarbonImmutable::now('UTC')->subDays(2)->toDateString(),
            siteIds: [$sede->id],
        ));

        $ayer = CarbonImmutable::now('America/Lima')->subDay()->toDateString();
        resolve(MaterializeObligations::class)($programacion, $ayer, $ayer);

        $obligacion = Obligation::query()->where('site_id', $sede->id)->firstOrFail();

        // El UPDATE masivo tiene que poder decir A QUIEN toco: sin eso no hay
        // aviso que mandar.
        $marcadas = resolve(MarkMissedObligations::class)();

        expect($marcadas)->toBe(1);

        $entrega = WebhookDelivery::query()->where('event', 'obligation.missed')->firstOrFail();

        expect($entrega->payload['data']['id'])->toBe($obligacion->id)
            ->and($entrega->payload['data']['site_id'])->toBe($sede->id);
    });
})->group('webhooks');

it('la pantalla da de alta un destino y ensena el secreto una vez', function (): void {
    enAcmeWebhooks(function (): void {
        duenaDeAcme();

        $componente = Livewire::test(Webhooks::class)
            ->set('url', URL_DESTINO)
            ->set('description', 'Nuestro ERP')
            ->set('events', ['submission.submitted', 'obligation.missed'])
            ->call('create')
            ->assertHasNoErrors();

        $destino = WebhookEndpoint::query()->firstOrFail();

        expect($destino->subscribed_events)->toBe(['submission.submitted', 'obligation.missed'])
            ->and($destino->is_active)->toBeTrue()
            ->and(mb_strlen($destino->secret))->toBeGreaterThanOrEqual(32);

        $componente->assertSet('justCreatedSecret', $destino->secret);

        // El secreto se guarda cifrado: quien lea la base no puede falsificar
        // avisos con el.
        $crudo = DB::connection('tenant')
            ->table('webhook_endpoints')
            ->where('id', $destino->id)
            ->value('secret');

        expect($crudo)->not->toBe($destino->secret);
    });
})->group('webhooks');

it('la pantalla explica por que rechaza una direccion, en castellano', function (): void {
    enAcmeWebhooks(function (): void {
        duenaDeAcme();

        Livewire::test(Webhooks::class)
            ->set('url', 'http://127.0.0.1/avisos')
            ->set('events', ['submission.submitted'])
            ->call('create')
            ->assertHasErrors('url');

        expect(WebhookEndpoint::query()->count())->toBe(0);
    });
})->group('webhooks');

it('quien no administra integraciones no entra a la pantalla', function (): void {
    enAcmeWebhooks(function (): void {
        $encargada = User::create([
            'name' => 'Encargada',
            'email' => 'encargada@acme.test',
            'password' => CLAVE_WEBHOOKS,
        ]);
        $encargada->assignRole(RoleName::SiteManager->value);
        auth()->login($encargada->fresh());

        Livewire::test(Webhooks::class)->assertForbidden();
    });
})->group('webhooks');
