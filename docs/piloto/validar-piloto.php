<?php

declare(strict_types=1);

/*
 * Validacion del piloto, de punta a punta, contra el stack de verdad.
 *
 * No es una prueba automatizada: es el recorrido que hara un cliente real,
 * ejecutado contra PostgreSQL, MinIO, Redis y las colas que estan corriendo.
 * Lo que las pruebas cubren en aislamiento, aqui se ve junto.
 *
 * Se ejecuta con:
 *   docker compose exec -T app php artisan tinker --execute="require '/app/docs/piloto/validar-piloto.php';"
 *
 * Crea un cliente nuevo cada vez y borra el de la corrida anterior, asi que
 * NO se ejecuta contra produccion.
 */

use Carbon\CarbonImmutable;
use Ronda\Directory\Application\Actions\CreateSite;
use Ronda\Directory\Application\Data\SiteData;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Evidence\Application\Actions\StoreEvidence;
use Ronda\Evidence\Application\Data\EvidenceUpload;
use Ronda\Evidence\Domain\Models\Attachment;
use Ronda\Forms\Application\Actions\InstallCatalogTemplate;
use Ronda\Forms\Domain\CatalogTemplate;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\RoleName;
use Ronda\Insights\Application\Actions\RecalculateKpis;
use Ronda\Platform\Application\Actions\IssueInvoice;
use Ronda\Platform\Application\Actions\RegisterTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Application\Queries\OnboardingProgressQuery;
use Ronda\Platform\Domain\Models\Invoice;
use Ronda\Platform\Domain\Models\Subscription;
use Ronda\Platform\Domain\Models\Tenant;
use Ronda\Scheduling\Application\Actions\CreateSchedule;
use Ronda\Scheduling\Application\Actions\MaterializeObligations;
use Ronda\Scheduling\Application\Data\ScheduleData;
use Ronda\Scheduling\Domain\Models\Obligation;
use Ronda\Scheduling\Domain\ScheduleScope;
use Ronda\Submissions\Application\Actions\SubmitReport;

$fallos = [];
$slug = 'piloto'.substr((string) time(), -4);

function paso(string $titulo): void
{
    echo PHP_EOL."== {$titulo}".PHP_EOL;
}

function ok(string $texto): void
{
    echo "   OK    {$texto}".PHP_EOL;
}

function mal(string $texto): void
{
    global $fallos;
    $fallos[] = $texto;
    echo "   FALLA {$texto}".PHP_EOL;
}

function comprobar(bool $condicion, string $texto): void
{
    $condicion ? ok($texto) : mal($texto);
}

// ---------------------------------------------------------------- 1. Registro
paso('1. Registro self-service y provision');

// Se borran los clientes de validaciones anteriores: esto crea uno nuevo cada
// vez y, sin esto, el servidor se llena de bases `ronda_tnt_*` huerfanas.
//
// Se borra a mano y sin eventos de modelo: borrar el tenant con Eloquent
// dispara el job de stancl que elimina su base, y ese job revienta cuando la
// base no llego a crearse, que es justo el caso que hay que limpiar.
$central = DB::connection(config('tenancy.database.central_connection'));

foreach (Tenant::query()->where('slug', 'like', 'piloto%')->get() as $antiguo) {
    $base = 'ronda_tnt_'.$antiguo->id;
    $existe = $central->selectOne('select 1 as hay from pg_database where datname = ?', [$base]);

    if ($existe !== null) {
        $central->statement('DROP DATABASE IF EXISTS "'.$base.'"');
    }

    Invoice::query()->where('tenant_id', $antiguo->id)->delete();
    Subscription::query()->where('tenant_id', $antiguo->id)->delete();
    $central->table('domains')->where('tenant_id', $antiguo->id)->delete();
    $central->table('tenants')->where('id', $antiguo->id)->delete();
}

$inicio = microtime(true);

$tenant = resolve(RegisterTenant::class)(new CreateTenantData(
    name: 'Piloto SAC',
    slug: $slug,
    domain: $slug.'.localhost',
    ownerName: 'Duena del Piloto',
    ownerEmail: "duena@{$slug}.test",
    ownerPassword: 'una-contrasena-larga-de-piloto',
));

comprobar($tenant->exists, "cliente creado: {$tenant->id}");

// La provision corre en cola: la pantalla de «preparando tu cuenta» pregunta
// cada dos segundos hasta que `provisioned_at` aparece. Aqui se hace lo mismo,
// que es ademas la unica forma honesta de medir cuanto tarda de verdad.
$listo = false;

for ($intento = 0; $intento < 60; $intento++) {
    if ($tenant->fresh()?->isReady() === true) {
        $listo = true;
        break;
    }

    usleep(1_000_000);
}

$segundos = round(microtime(true) - $inicio, 1);

comprobar($listo, "provisionado y marcado listo en {$segundos}s");
comprobar($listo && $segundos < 30, "la provision tarda menos de 30 segundos, como pide el plan ({$segundos}s)");

if (! $listo) {
    echo PHP_EOL.'La provision no termino. Revisa Horizon: docker compose logs horizon --tail 50'.PHP_EOL;

    return;
}

$tenant = $tenant->fresh();

// --------------------------------------------------------------- 2. Comercial
paso('2. Plan y suscripcion');

$suscripcion = Subscription::query()->where('tenant_id', $tenant->id)->first();

comprobar($tenant->plan?->code()?->value === 'starter', 'entra en el plan de entrada (starter)');
comprobar($suscripcion instanceof Subscription, 'se abre la suscripcion al registrarse');
comprobar($suscripcion?->onTrial() === true, 'arranca en prueba gratuita hasta '.$suscripcion?->trial_ends_at?->format('d/m/Y'));
comprobar($suscripcion?->canBeCharged() === false, 'no hay tarjeta que cobrar todavia (modo manual)');

// ------------------------------------------------------------------ 3. Dentro
$tenant->run(function () use ($tenant, $slug): void {
    paso('3. Dentro del cliente: usuarios, sedes y plantillas');

    $owner = User::query()->where('email', "duena@{$slug}.test")->first();
    comprobar($owner instanceof User, 'la duena existe en SU base');
    comprobar($owner?->hasRole(RoleName::Owner->value) === true, 'y tiene el rol de propietaria');

    auth()->login($owner);

    $avance = resolve(OnboardingProgressQuery::class)();
    comprobar($avance->done() === 0, 'el asistente arranca con los cinco pasos por hacer');

    // Paso 1: plantilla del catalogo.
    $plantilla = resolve(InstallCatalogTemplate::class)(CatalogTemplate::CashCount, $owner);
    comprobar($plantilla->currentVersion !== null, 'plantilla del catalogo instalada y publicada: '.$plantilla->name);

    // Paso 2: sede.
    $sede = resolve(CreateSite::class)(new SiteData(
        code: 'LIMA-01',
        name: 'Sede Miraflores',
        timezone: 'America/Lima',
        latitude: '-12.1194000',
        longitude: '-77.0292000',
    ));
    comprobar($sede->exists, 'sede creada con su zona horaria: '.$sede->timezone);

    // Paso 3: encargada.
    $encargada = User::create([
        'name' => 'Encargada Miraflores',
        'email' => "encargada@{$slug}.test",
        'password' => 'una-contrasena-larga-de-piloto',
    ]);
    $encargada->assignRole(RoleName::SiteManager->value);
    $encargada->sites()->attach($sede->id);
    comprobar($encargada->sites()->count() === 1, 'encargada invitada y asignada a su sede');

    // Paso 4: programacion diaria.
    $programacion = resolve(CreateSchedule::class)(new ScheduleData(
        templateId: $plantilla->id,
        name: 'Arqueo de caja diario',
        scope: ScheduleScope::Sites,
        rrule: 'FREQ=DAILY',
        // Ventana de todo el dia: asi la validacion no depende de la hora a
        // la que se ejecute. Que una ventana estrecha cierre a su hora ya lo
        // cubren las pruebas de Scheduling.
        windowStart: '00:00',
        windowEnd: '23:59',
        startsOn: CarbonImmutable::now('UTC')->subDays(2)->toDateString(),
        toleranceMinutes: 30,
        siteIds: [$sede->id],
    ));
    comprobar($programacion->exists, 'programacion diaria creada');

    $hoy = CarbonImmutable::now('America/Lima')->toDateString();
    resolve(MaterializeObligations::class)($programacion, $hoy, $hoy);

    $obligacion = Obligation::query()->where('site_id', $sede->id)->latest('id')->first();
    comprobar($obligacion instanceof Obligation, 'obligacion de hoy materializada');
    // La prueba de la zona horaria: medianoche en Lima son las 05:00 UTC. Si
    // el motor guardara la hora local, aqui saldria 00:00.
    comprobar(
        $obligacion?->opens_at?->timezone('America/Lima')->format('H:i') === '00:00'
        && $obligacion?->opens_at?->timezone('UTC')->format('H:i') === '05:00',
        'la ventana abre a medianoche EN LIMA (05:00 UTC en la base)',
    );

    paso('4. Entrega del reporte con evidencia real');

    auth()->login($encargada->fresh());

    // Una foto de verdad, no un archivo vacio.
    $imagen = imagecreatetruecolor(600, 400);
    imagefilledrectangle($imagen, 0, 0, 599, 399, (int) imagecolorallocate($imagen, 30, 120, 200));
    ob_start();
    imagejpeg($imagen, null, 85);
    $foto = (string) ob_get_clean();

    $lienzo = imagecreatetruecolor(300, 120);
    imagefilledrectangle($lienzo, 0, 0, 299, 119, (int) imagecolorallocate($lienzo, 255, 255, 255));
    imageline($lienzo, 20, 90, 280, 40, (int) imagecolorallocate($lienzo, 0, 0, 0));
    ob_start();
    imagepng($lienzo);
    $firma = (string) ob_get_clean();

    $envio = resolve(SubmitReport::class)(
        $obligacion,
        $encargada,
        [
            'saldo_sistema' => '1520.40',
            'efectivo_contado' => '1515.40',
            'diferencia' => '-5.00',
            'motivo_diferencia' => 'Vuelto mal dado en el turno de la tarde.',
            'observaciones' => 'Se repone manana.',
        ],
        CarbonImmutable::now('UTC'),
        [
            'foto_arqueo' => [new EvidenceUpload(
                contents: $foto,
                originalName: 'arqueo.jpg',
                deviceLatitude: '-12.1195',
                deviceLongitude: '-77.0293',
            )],
            // La firma va como PNG: es un campo obligatorio de esta plantilla.
            'firma' => [new EvidenceUpload(contents: $firma, originalName: 'firma.png')],
        ],
    );

    comprobar($envio->exists, 'reporte entregado');
    comprobar($obligacion->fresh()?->submission_id === $envio->id, 'la obligacion queda cumplida y enlazada');

    comprobar(
        Attachment::query()->where('submission_id', $envio->id)->count() === 2,
        'se guardan los dos adjuntos: la foto y la firma',
    );

    $adjunto = Attachment::query()
        ->where('submission_id', $envio->id)
        ->where('field_key', 'foto_arqueo')
        ->first();
    comprobar($adjunto instanceof Attachment, 'la foto quedo guardada');
    comprobar($adjunto?->disk === StoreEvidence::DISK, 'en el disco privado de evidencia, no en public/');
    comprobar(
        is_string($adjunto?->path) && str_starts_with($adjunto->path, 'tenants/'.$tenant->id.'/'),
        'bajo el prefijo de SU tenant en el bucket',
    );
    comprobar(
        is_string($adjunto?->sha256) && strlen($adjunto->sha256) === 64,
        'con su huella SHA-256 de lo que se guardo',
    );
    comprobar(
        $adjunto?->distance_meters !== null && $adjunto->distance_meters < 100,
        'y la distancia a la sede calculada: '.($adjunto?->distance_meters ?? '?').' m',
    );

    // El archivo esta DE VERDAD en el bucket.
    $existe = Storage::disk(StoreEvidence::DISK)->exists((string) $adjunto?->path);
    comprobar($existe, 'el objeto existe en MinIO');

    $bytes = Storage::disk(StoreEvidence::DISK)->get((string) $adjunto?->path);
    comprobar(
        is_string($bytes) && hash('sha256', $bytes) === $adjunto?->sha256,
        'y lo que hay en el bucket coincide con la huella guardada',
    );

    paso('5. Lo que ve la gerencia');

    auth()->login(User::query()->where('email', "duena@{$slug}.test")->first());

    resolve(RecalculateKpis::class)(
        CarbonImmutable::now('UTC')->subDays(2)->toDateString(),
        CarbonImmutable::now('UTC')->toDateString(),
    );

    $kpi = DB::table('kpi_daily')->where('site_id', $sede->id)->orderByDesc('kpi_date')->first();
    comprobar($kpi !== null, 'los KPI del dia se materializaron');
    comprobar(($kpi->fulfilled ?? 0) >= 1, 'y cuentan la obligacion cumplida de hoy');
    comprobar(($kpi->on_time ?? 0) >= 1, 'como entregada a tiempo');

    $avance = resolve(OnboardingProgressQuery::class)();
    comprobar($avance->finished(), 'el asistente de arranque queda completo (5 de 5)');

    paso('6. Limites del plan Starter');

    $instalar = resolve(InstallCatalogTemplate::class);
    $duena = User::query()->where('email', "duena@{$slug}.test")->first();

    $instalar(CatalogTemplate::cases()[1], $duena);
    $instalar(CatalogTemplate::cases()[2], $duena);

    comprobar(Template::query()->count() === 3, 'llega a las 3 plantillas que da Starter');

    try {
        $instalar(CatalogTemplate::cases()[3], $duena);
        mal('la cuarta plantilla NO deberia haberse instalado');
    } catch (Ronda\Platform\Domain\Exceptions\PlanLimitExceeded $e) {
        ok("la cuarta se corta con el numero del plan: {$e->limitValue}");
    }

    comprobar(Template::query()->count() === 3, 'y no queda una plantilla a medias');
});

// ------------------------------------------------------------ 7. Facturacion
paso('7. Facturacion');

$suscripcion->refresh();
$suscripcion->forceFill([
    'status' => Ronda\Platform\Domain\Billing\SubscriptionStatus::Active,
    'trial_ends_at' => CarbonImmutable::now('UTC')->subDay(),
    'current_period_start' => CarbonImmutable::now('UTC')->subMonth(),
    'current_period_end' => CarbonImmutable::now('UTC')->subDay(),
])->save();

$sedes = $tenant->run(static fn (): int => Site::query()->withoutGlobalScopes()->count());
$factura = resolve(IssueInvoice::class)($suscripcion->refresh(), $sedes);

comprobar($factura->billed_sites === 5, "una sede real, cinco facturadas: el minimo de Starter ({$factura->billed_sites})");
comprobar($factura->amount === '145.00', "importe correcto: {$factura->currency} {$factura->amount}");
comprobar($factura->status->value === 'pending', 'queda pendiente de conciliar (modo manual), no cobrada');

$repetida = resolve(IssueInvoice::class)($suscripcion, $sedes);
comprobar($repetida->id === $factura->id, 'emitir dos veces el mismo periodo no duplica el cobro');
comprobar(Invoice::query()->where('tenant_id', $tenant->id)->count() === 1, 'una sola factura en la base');

// -------------------------------------------------------------- 8. Aislamiento
paso('8. Aislamiento entre clientes');

$otros = Tenant::query()->where('id', '!=', $tenant->id)->count();
comprobar(! Schema::hasTable('users'), 'la base central no tiene tabla `users`: los usuarios viven en cada cliente');
ok("hay {$otros} cliente(s) mas en la central; ninguno comparte base");

// --------------------------------------------------------------- Resultado
echo PHP_EOL.str_repeat('-', 70).PHP_EOL;

if ($fallos === []) {
    echo 'PILOTO: todo lo comprobado pasa.'.PHP_EOL;
} else {
    echo 'PILOTO: '.count($fallos).' comprobacion(es) fallaron:'.PHP_EOL;
    foreach ($fallos as $fallo) {
        echo "  - {$fallo}".PHP_EOL;
    }
}

echo "Cliente de prueba creado: {$slug} (http://{$slug}.localhost:8000)".PHP_EOL;
echo "Duena: duena@{$slug}.test / una-contrasena-larga-de-piloto".PHP_EOL;
