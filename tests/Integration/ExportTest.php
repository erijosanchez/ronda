<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Evidence\Application\Actions\StoreEvidence;
use Ronda\Forms\Application\Actions\CreateTemplate;
use Ronda\Forms\Application\Actions\PublishTemplateVersion;
use Ronda\Forms\Application\Data\TemplateData;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Forms\Domain\ValueObjects\FormSchema;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\RoleName;
use Ronda\Insights\Application\Actions\RequestExport;
use Ronda\Insights\Application\Data\ExportRequest;
use Ronda\Insights\Application\Jobs\GenerateSubmissionExportJob;
use Ronda\Insights\Domain\ExportStatus;
use Ronda\Insights\Domain\Models\Export;
use Ronda\Insights\Presentation\Livewire\ExportList;
use Ronda\Notifications\Infrastructure\Notifications\OperationalNotification;
use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Models\Tenant;
use Ronda\Scheduling\Application\Actions\CreateSchedule;
use Ronda\Scheduling\Application\Actions\MaterializeObligations;
use Ronda\Scheduling\Application\Data\ScheduleData;
use Ronda\Scheduling\Domain\Models\Obligation;
use Ronda\Scheduling\Domain\ScheduleScope;
use Ronda\Submissions\Application\Actions\SubmitReport;

// Exportaciones. RONDA-PLAN-MAESTRO.md sec. 13
//
// «En cola, con notificacion al terminar; nunca en la peticion.» Lo que se
// prueba: que el archivo sale con lo que tiene que salir, que respeta la
// frontera por sede aunque el job corra sin sesion, y que solo lo descarga quien
// lo pidio.

const CLAVE_EXPORT = 'una-contrasena-larga-de-prueba';

beforeEach(function (): void {
    $this->tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Acme',
        slug: 'acme',
        domain: 'acme.ronda.test',
        ownerName: 'Duena de Acme',
        ownerEmail: 'owner@acme.test',
        ownerPassword: CLAVE_EXPORT,
    ));

    Storage::fake(StoreEvidence::DISK);
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();

    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    dropTenantDatabase($tenant);
});

function enAcmeExport(Closure $fn): mixed
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;

    return $tenant->run($fn);
}

function personaExport(string $email, RoleName $rol, ?Site $sede = null): User
{
    $user = User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => CLAVE_EXPORT]);
    $user->assignRole($rol->value);

    if ($sede instanceof Site) {
        $user->sites()->attach($sede->id);
    }

    return $user->fresh();
}

/**
 * Un arqueo entregado el 15 de septiembre en esa sede, con su monto.
 */
function arqueoExportable(Site $sede, string $monto = '250.50'): Template
{
    $owner = User::query()->where('email', 'owner@acme.test')->firstOrFail();

    $plantilla = resolve(CreateTemplate::class)(new TemplateData(code: 'ARQ-'.$sede->id, name: 'Arqueo '.$sede->code));
    resolve(PublishTemplateVersion::class)($plantilla, FormSchema::fromArray([
        ['key' => 'monto', 'type' => 'money', 'label' => 'Monto', 'required' => true, 'reportable' => true],
        ['key' => 'notas', 'type' => 'text', 'label' => 'Notas'],
    ]), $owner);

    $programacion = resolve(CreateSchedule::class)(new ScheduleData(
        templateId: $plantilla->id,
        name: 'Arqueo '.$sede->code,
        scope: ScheduleScope::Sites,
        rrule: 'FREQ=DAILY',
        windowStart: '08:00',
        windowEnd: '18:00',
        startsOn: '2026-09-01',
        skipHolidays: false,
        siteIds: [$sede->id],
    ));
    resolve(MaterializeObligations::class)($programacion, '2026-09-15', '2026-09-15');

    $obligacion = Obligation::query()->where('site_id', $sede->id)->firstOrFail();
    $encargada = personaExport('encargada-'.$sede->id.'@acme.test', RoleName::SiteManager, $sede);

    resolve(SubmitReport::class)(
        $obligacion,
        $encargada,
        ['monto' => $monto, 'notas' => 'Sin novedad'],
        CarbonImmutable::parse('2026-09-15 20:00', 'UTC'),
    );

    return $plantilla->refresh();
}

/**
 * Lee el XLSX guardado y devuelve sus filas.
 *
 * @return list<list<string>>
 */
function filasDelArchivo(Export $export): array
{
    $temporal = tempnam(sys_get_temp_dir(), 'ronda-test-').'.xlsx';
    file_put_contents($temporal, Storage::disk((string) $export->disk)->get((string) $export->path));

    $lector = new Reader;
    $lector->open($temporal);

    $filas = [];

    foreach ($lector->getSheetIterator() as $hoja) {
        foreach ($hoja->getRowIterator() as $fila) {
            $filas[] = array_map(strval(...), $fila->toArray());
        }

        break;
    }

    $lector->close();
    @unlink($temporal);

    return $filas;
}

/**
 * Ejecuta el job en el acto.
 *
 * No se usa `dispatch_sync`: con la cola fingida, Laravel lo manda a la
 * conexion `sync`, que tambien esta fingida, y el job no corre. Llamar a
 * `handle` por el contenedor resuelve sus dependencias igual que en produccion.
 */
function ejecutarExport(Export $export): void
{
    app()->call([new GenerateSubmissionExportJob($export->id), 'handle']);
}

/**
 * @param  list<int>  $sedes
 */
function pedirExport(array $sedes, User $quien, ?int $templateId = null, ?string $state = null): Export
{
    return resolve(RequestExport::class)(
        new ExportRequest(from: '2026-09-15', to: '2026-09-15', siteIds: $sedes, templateId: $templateId, state: $state),
        $quien,
    );
}

it('encola la exportacion en vez de generarla en la peticion', function (): void {
    enAcmeExport(function (): void {
        Queue::fake();

        $sede = Site::factory()->create();
        arqueoExportable($sede);
        $supervisora = personaExport('supervisora@acme.test', RoleName::Supervisor, $sede);

        $export = pedirExport([$sede->id], $supervisora);

        expect($export->status)->toBe(ExportStatus::Queued)
            ->and($export->path)->toBeNull();

        // Y en su propia cola: una exportacion no retrasa un aviso de SLA.
        Queue::assertPushedOn('reports', GenerateSubmissionExportJob::class);
    });
})->group('insights');

it('escribe el archivo con una fila por envio y los campos reportables', function (): void {
    enAcmeExport(function (): void {
        // La cola es sincrona en pruebas: se finge para poder lanzar el job a
        // mano y ver el antes y el despues.
        Queue::fake();

        $sede = Site::factory()->create(['name' => 'Sede Centro']);
        $plantilla = arqueoExportable($sede, monto: '250.50');
        $supervisora = personaExport('supervisora@acme.test', RoleName::Supervisor, $sede);

        $export = pedirExport([$sede->id], $supervisora, templateId: $plantilla->id);
        ejecutarExport($export);

        $export->refresh();

        expect($export->status)->toBe(ExportStatus::Completed)
            ->and($export->rows)->toBe(1)
            ->and($export->bytes)->toBeGreaterThan(0)
            ->and($export->path)->toStartWith('tenants/')
            ->and($export->file_name)->toBe('envios-2026-09-15-a-2026-09-15.xlsx');

        $filas = filasDelArchivo($export);

        // Cabecera + una fila.
        expect($filas)->toHaveCount(2)
            // Al filtrar por plantilla, sus campos reportables son columnas.
            ->and($filas[0])->toContain('Monto')
            ->and($filas[1])->toContain('Sede Centro')
            ->and($filas[1])->toContain('250.5000')
            // El dia es el de la obligacion, no el de la entrega.
            ->and($filas[1][0])->toBe('2026-09-15');
    });
})->group('insights');

it('avisa a quien la pidio cuando esta lista', function (): void {
    enAcmeExport(function (): void {
        Queue::fake();

        $sede = Site::factory()->create();
        arqueoExportable($sede);
        $supervisora = personaExport('supervisora@acme.test', RoleName::Supervisor, $sede);

        $export = pedirExport([$sede->id], $supervisora);

        Notification::fake();
        ejecutarExport($export);

        Notification::assertSentTo($supervisora, OperationalNotification::class, function (OperationalNotification $aviso) use ($supervisora): bool {
            $datos = $aviso->toArray($supervisora);

            return $datos['topic'] === 'export_ready'
                && str_contains((string) $datos['body'], '1');
        });
    });
})->group('insights');

it('respeta la frontera por sede congelada al encargarla', function (): void {
    // El job corre sin sesion: si la frontera dependiera del scope global, una
    // exportacion se llevaria el parque entero.
    enAcmeExport(function (): void {
        Queue::fake();

        $mia = Site::factory()->create(['name' => 'Sede Mia']);
        $ajena = Site::factory()->create(['name' => 'Sede Ajena']);
        arqueoExportable($mia);
        arqueoExportable($ajena);

        $supervisora = personaExport('supervisora@acme.test', RoleName::Supervisor, $mia);
        auth()->login($supervisora);

        // Se pide desde la pantalla, que es quien congela las sedes visibles.
        Livewire::test(ExportList::class)
            ->set('from', '2026-09-15')
            ->set('to', '2026-09-15')
            ->call('request')
            ->assertHasNoErrors();

        $export = Export::query()->sole();

        expect($export->filters['site_ids'])->toBe([$mia->id]);

        ejecutarExport($export);

        $filas = filasDelArchivo($export->refresh());

        expect($export->rows)->toBe(1)
            ->and($filas[1])->toContain('Sede Mia')
            ->and(implode('|', $filas[1]))->not->toContain('Sede Ajena');
    });
})->group('insights');

it('deja constancia del motivo si falla', function (): void {
    enAcmeExport(function (): void {
        Queue::fake();

        $sede = Site::factory()->create();
        arqueoExportable($sede);
        $supervisora = personaExport('supervisora@acme.test', RoleName::Supervisor, $sede);

        // Una fecha imposible en los filtros: la consulta revienta y el job
        // tiene que dejar constancia, no morir en silencio.
        $export = pedirExport([$sede->id], $supervisora);
        $export->forceFill(['filters' => [...$export->filters, 'from' => 'no-es-una-fecha']])->save();

        $lanzo = false;

        try {
            ejecutarExport($export);
        } catch (Throwable) {
            // El job relanza a proposito: asi la cola tambien la marca como
            // fallida y se puede reintentar.
            $lanzo = true;
        }

        expect($lanzo)->toBeTrue();

        expect($export->refresh()->status)->toBe(ExportStatus::Failed)
            ->and($export->error)->not->toBeNull();
    });
})->group('insights');

it('solo la descarga quien la pidio', function (): void {
    $ids = enAcmeExport(function (): array {
        Queue::fake();

        $sede = Site::factory()->create();
        arqueoExportable($sede);
        $supervisora = personaExport('supervisora@acme.test', RoleName::Supervisor, $sede);
        personaExport('otra@acme.test', RoleName::Supervisor, $sede);

        $export = pedirExport([$sede->id], $supervisora);
        ejecutarExport($export);

        return [$export->id];
    });

    $url = 'http://acme.ronda.test';

    // Quien la pidio: se la lleva.
    $this->post($url.'/login', ['email' => 'supervisora@acme.test', 'password' => CLAVE_EXPORT]);
    $this->get($url.'/exportaciones/'.$ids[0].'/descargar')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    // Otra persona con el mismo rol: no.
    $this->post($url.'/logout');
    $this->post($url.'/login', ['email' => 'otra@acme.test', 'password' => CLAVE_EXPORT]);
    $this->get($url.'/exportaciones/'.$ids[0].'/descargar')->assertForbidden();
})->group('insights');

it('no deja pedir exportaciones a quien no ve reportes', function (): void {
    enAcmeExport(function (): void {
        $sede = Site::factory()->create();
        auth()->login(personaExport('encargada@acme.test', RoleName::SiteManager, $sede));

        Livewire::test(ExportList::class)->assertForbidden();
    });
})->group('insights');

it('sirve la pantalla de exportaciones dentro del layout', function (): void {
    $url = 'http://acme.ronda.test';
    $this->post($url.'/login', ['email' => 'owner@acme.test', 'password' => CLAVE_EXPORT]);

    $this->get($url.'/exportaciones')
        ->assertOk()
        ->assertSee('Exportaciones')
        ->assertSee('Pedir exportación');
})->group('insights');
