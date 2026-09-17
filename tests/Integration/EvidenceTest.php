<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Evidence\Application\Actions\StoreEvidence;
use Ronda\Evidence\Application\Data\EvidenceUpload;
use Ronda\Evidence\Domain\EvidenceKind;
use Ronda\Evidence\Domain\Models\Attachment;
use Ronda\Evidence\Domain\Services\ExifReader;
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
use Ronda\Scheduling\Domain\States\Pending;
use Ronda\Submissions\Application\Actions\SubmitReport;
use Ronda\Submissions\Domain\Exceptions\InvalidAnswers;
use Ronda\Submissions\Domain\Models\Submission;
use Ronda\Submissions\Presentation\Livewire\SubmissionForm;
use Tests\Support\EvidenceFixtures;

// Evidencia con valor probatorio. RONDA-PLAN-MAESTRO.md sec. 9.5, 10.4 y
// ADR 0009.
//
// Lo que se prueba aqui es lo que solo existe con base, bucket y HTTP: que el
// archivo guardado es el que dice el hash, que lo rechazado no deja rastro ni en
// la base ni en el bucket, y que la URL firmada no sirve fuera de su tenant, sin
// sesion, caducada o a quien ya no puede ver el envio.

const CLAVE_EVIDENCIA = 'una-contrasena-larga-de-prueba';

beforeEach(function (): void {
    $this->tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Acme',
        slug: 'acme',
        domain: 'acme.ronda.test',
        ownerName: 'Duena de Acme',
        ownerEmail: 'owner@acme.test',
        ownerPassword: CLAVE_EVIDENCIA,
    ));

    Storage::fake(StoreEvidence::DISK);
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();

    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    dropTenantDatabase($tenant);
});

function enAcmeEvidencia(Closure $fn): mixed
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;

    return $tenant->run($fn);
}

function ahoraEvidencia(): CarbonImmutable
{
    // 15:00 en Lima, dentro de la ventana 08:00-18:00 del 16 de septiembre.
    return CarbonImmutable::parse('2026-09-16 20:00:00', 'UTC');
}

/**
 * Sede en la Plaza de Armas de Lima con una obligacion abierta de una
 * plantilla que pide foto obligatoria, firma y un monto.
 */
function obligacionConFoto(?Site $sede = null): Obligation
{
    $sede ??= Site::factory()->create(['latitude' => '-12.0464000', 'longitude' => '-77.0428000']);
    // Vale para cualquier tenant de la prueba: cada uno tiene su propietaria.
    $owner = User::query()->where('email', 'like', 'owner@%')->firstOrFail();

    $plantilla = resolve(CreateTemplate::class)(new TemplateData(code: 'APERTURA-'.$sede->id, name: 'Apertura'));
    resolve(PublishTemplateVersion::class)($plantilla, FormSchema::fromArray([
        ['key' => 'foto', 'type' => 'photo', 'label' => 'Foto del local', 'required' => true],
        ['key' => 'firma', 'type' => 'signature', 'label' => 'Firma'],
        ['key' => 'monto', 'type' => 'money', 'label' => 'Monto'],
    ]), $owner);

    $programacion = resolve(CreateSchedule::class)(new ScheduleData(
        templateId: $plantilla->id,
        name: 'Apertura diaria',
        scope: ScheduleScope::Sites,
        rrule: 'FREQ=DAILY',
        windowStart: '08:00',
        windowEnd: '18:00',
        startsOn: '2026-09-01',
        skipHolidays: false,
        siteIds: [$sede->id],
    ));

    resolve(MaterializeObligations::class)($programacion, '2026-09-16', '2026-09-16');

    return Obligation::query()->where('site_id', $sede->id)->firstOrFail();
}

function encargadaConEvidencia(Site $sede, string $email = 'encargada@acme.test'): User
{
    $user = User::create(['name' => 'Encargada', 'email' => $email, 'password' => CLAVE_EVIDENCIA]);
    $user->assignRole(RoleName::SiteManager->value);
    $user->sites()->attach($sede->id);

    return $user->fresh();
}

/**
 * @param  array<string, list<EvidenceUpload>>  $evidencia
 */
function entregarConEvidencia(Obligation $obligacion, User $autora, array $evidencia, array $respuestas = []): Submission
{
    return resolve(SubmitReport::class)($obligacion, $autora, $respuestas, ahoraEvidencia(), $evidencia);
}

it('guarda la foto saneada, con su hash, su ubicacion y la distancia a la sede', function (): void {
    enAcmeEvidencia(function (): void {
        $obligacion = obligacionConFoto();
        $autora = encargadaConEvidencia($obligacion->site);

        // Foto tomada en la Plaza de Armas a las 09:12 de Lima.
        $envio = entregarConEvidencia($obligacion, $autora, [
            'foto' => [new EvidenceUpload(EvidenceFixtures::jpegWithExif(), 'IMG_0001.jpg', ipAddress: '10.0.0.7')],
        ]);

        $adjunto = Attachment::query()->sole();
        $guardado = Storage::disk(StoreEvidence::DISK)->get($adjunto->path);

        expect($envio->data['foto'])->toBe([(string) $adjunto->id])
            ->and($adjunto->kind)->toBe(EvidenceKind::Photo)
            ->and($adjunto->path)->toStartWith('tenants/'.tenant()?->getTenantKey().'/')
            ->and($adjunto->original_name)->toBe('IMG_0001.jpg')
            ->and($adjunto->mime_type)->toBe('image/jpeg')
            // El hash es del archivo tal como quedo guardado.
            ->and($adjunto->sha256)->toBe(hash('sha256', (string) $guardado))
            ->and($adjunto->bytes)->toBe(strlen((string) $guardado))
            ->and($adjunto->location_source)->toBe('exif')
            ->and($adjunto->distance_meters)->toBeLessThan(5)
            ->and($adjunto->captured_at?->toDateTimeString())->toBe('2026-09-16 14:12:33')
            ->and($adjunto->isStaleAt($envio->submitted_at))->toBeFalse()
            // Lo guardado ya no lleva EXIF: ni coordenadas ni nada mas.
            ->and((new ExifReader)->read((string) $guardado, 'image/jpeg')->location)->toBeNull();
    });
})->group('evidence');

it('senala una foto tomada lejos del local y otra tomada dias antes', function (): void {
    enAcmeEvidencia(function (): void {
        $obligacion = obligacionConFoto();
        $autora = encargadaConEvidencia($obligacion->site);

        // Miraflores, a unos 8 km, y del 10 de septiembre.
        entregarConEvidencia($obligacion, $autora, [
            'foto' => [new EvidenceUpload(
                EvidenceFixtures::jpegWithExif(dateTimeOriginal: '2026:09:10 09:00:00', latDms: [12, 7, 1596], lngDms: [77, 1, 4692]),
                'vieja.jpg',
            )],
        ]);

        $adjunto = Attachment::query()->sole();

        expect($adjunto->isFarFromSite())->toBeTrue()
            ->and($adjunto->distance_meters)->toBeGreaterThan(8000)
            ->and($adjunto->isStaleAt(ahoraEvidencia()))->toBeTrue();
    });
})->group('evidence');

it('usa la ubicacion del dispositivo cuando la foto no trae GPS', function (): void {
    enAcmeEvidencia(function (): void {
        $obligacion = obligacionConFoto();
        $autora = encargadaConEvidencia($obligacion->site);

        entregarConEvidencia($obligacion, $autora, [
            'foto' => [new EvidenceUpload(EvidenceFixtures::png(), 'captura.png', '-12.0465000', '-77.0429000')],
        ]);

        $adjunto = Attachment::query()->sole();

        expect($adjunto->location_source)->toBe('device')
            ->and($adjunto->captured_at)->toBeNull()
            ->and($adjunto->distance_meters)->toBeLessThan(50);
    });
})->group('evidence');

it('rechaza un archivo disfrazado de foto sin dejar nada en la base ni en el bucket', function (): void {
    enAcmeEvidencia(function (): void {
        $obligacion = obligacionConFoto();
        $autora = encargadaConEvidencia($obligacion->site);

        expect(fn (): Submission => entregarConEvidencia($obligacion, $autora, [
            'foto' => [new EvidenceUpload(EvidenceFixtures::htmlDisguisedAsPhoto(), 'foto.jpg')],
        ]))->toThrow(function (InvalidAnswers $e): void {
            expect($e->errors)->toHaveKey('foto');
        });

        expect(Submission::query()->count())->toBe(0)
            ->and(Attachment::query()->count())->toBe(0)
            ->and($obligacion->refresh()->status)->toBeInstanceOf(Pending::class)
            ->and(Storage::disk(StoreEvidence::DISK)->allFiles())->toBe([]);
    });
})->group('evidence');

it('borra del bucket lo ya subido si la entrega falla despues', function (): void {
    // El bucket no participa en la transaccion. La primera foto se sube bien;
    // la segunda es un PDF en un campo de foto. Sin la compensacion, la primera
    // quedaria en el bucket sin envio que la reclame.
    enAcmeEvidencia(function (): void {
        $obligacion = obligacionConFoto();
        $autora = encargadaConEvidencia($obligacion->site);

        expect(fn (): Submission => entregarConEvidencia($obligacion, $autora, [
            'foto' => [
                new EvidenceUpload(EvidenceFixtures::jpeg(), 'buena.jpg'),
                new EvidenceUpload("%PDF-1.4\n%fake\n", 'mala.jpg'),
            ],
        ]))->toThrow(InvalidAnswers::class);

        expect(Attachment::query()->count())->toBe(0)
            ->and(Storage::disk(StoreEvidence::DISK)->allFiles())->toBe([]);
    });
})->group('evidence');

it('exige la foto obligatoria', function (): void {
    enAcmeEvidencia(function (): void {
        $obligacion = obligacionConFoto();
        $autora = encargadaConEvidencia($obligacion->site);

        expect(fn (): Submission => entregarConEvidencia($obligacion, $autora, [], ['foto' => ['1']]))
            ->toThrow(function (InvalidAnswers $e): void {
                expect($e->errors)->toHaveKey('foto');
            });
    });
})->group('evidence');

it('entrega desde la pantalla con foto subida, firma dibujada y ubicacion', function (): void {
    enAcmeEvidencia(function (): void {
        $obligacion = obligacionConFoto();
        $autora = encargadaConEvidencia($obligacion->site);
        CarbonImmutable::setTestNow(ahoraEvidencia());
        auth()->login($autora);

        $foto = UploadedFile::fake()->createWithContent('local.jpg', EvidenceFixtures::jpeg());

        Livewire::test(SubmissionForm::class, ['obligation' => $obligacion])
            ->set('uploads.foto', [$foto])
            ->set('signatures.firma', EvidenceFixtures::pngDataUrl())
            ->set('latitude', '-12.0464500')
            ->set('longitude', '-77.0428500')
            ->call('submit')
            ->assertHasNoErrors();

        $envio = Submission::query()->sole();
        $firma = Attachment::query()->where('kind', EvidenceKind::Signature->value)->sole();
        $foto = Attachment::query()->where('kind', EvidenceKind::Photo->value)->sole();

        expect($envio->data['firma'])->toBe([(string) $firma->id])
            ->and($envio->data['foto'])->toBe([(string) $foto->id])
            ->and($firma->mime_type)->toBe('image/png')
            ->and($firma->ip_address)->not->toBeNull()
            ->and($foto->location_source)->toBe('device')
            ->and(Storage::disk(StoreEvidence::DISK)->allFiles())->toHaveCount(2);
    });
})->group('evidence');

it('rechaza una firma que no es un PNG', function (): void {
    enAcmeEvidencia(function (): void {
        $obligacion = obligacionConFoto();
        $autora = encargadaConEvidencia($obligacion->site);
        CarbonImmutable::setTestNow(ahoraEvidencia());
        auth()->login($autora);

        Livewire::test(SubmissionForm::class, ['obligation' => $obligacion])
            ->set('uploads.foto', [UploadedFile::fake()->createWithContent('local.jpg', EvidenceFixtures::jpeg())])
            ->set('signatures.firma', 'data:image/png;base64,'.base64_encode(EvidenceFixtures::htmlDisguisedAsPhoto()))
            ->call('submit')
            ->assertHasErrors(['answers.firma']);

        expect(Submission::query()->count())->toBe(0)
            ->and(Storage::disk(StoreEvidence::DISK)->allFiles())->toBe([]);
    });
})->group('evidence');

/**
 * Deja un envio con foto en acme y devuelve [id del envio, id de la sede].
 *
 * @return array{0: int, 1: int}
 */
function envioConFotoEnAcme(): array
{
    return enAcmeEvidencia(function (): array {
        $obligacion = obligacionConFoto();
        $autora = encargadaConEvidencia($obligacion->site);
        $envio = entregarConEvidencia($obligacion, $autora, [
            'foto' => [new EvidenceUpload(EvidenceFixtures::jpegWithExif(), 'IMG_0001.jpg')],
        ]);

        return [$envio->id, $obligacion->site_id];
    });
}

function entrarEnAcme(string $email): void
{
    test()->post('http://acme.ronda.test/logout');
    test()->post('http://acme.ronda.test/login', ['email' => $email, 'password' => CLAVE_EVIDENCIA]);
}

/**
 * La URL firmada tal como la emite la ficha del envio.
 */
function urlFirmadaDeLaFicha(int $envioId): string
{
    $html = (string) test()->get('http://acme.ronda.test/envios/'.$envioId)->assertOk()->getContent();

    preg_match('#https?://acme\.ronda\.test/evidencia/\d+\?[^"\s]+#', html_entity_decode($html), $m);

    expect($m)->not->toBeEmpty();

    return $m[0];
}

it('sirve la evidencia por una URL firmada que muestra la ficha del envio', function (): void {
    [$envioId] = envioConFotoEnAcme();
    entrarEnAcme('encargada@acme.test');

    $url = urlFirmadaDeLaFicha($envioId);
    $respuesta = $this->get($url);

    $adjunto = enAcmeEvidencia(fn (): Attachment => Attachment::query()->sole());

    $respuesta->assertOk()
        ->assertHeader('X-Evidence-SHA256', $adjunto->sha256)
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($respuesta->headers->get('Cache-Control'))->toContain('no-store')
        ->and(hash('sha256', (string) $respuesta->streamedContent()))->toBe($adjunto->sha256);
})->group('evidence');

it('no sirve la evidencia sin firma, con la firma alterada ni caducada', function (): void {
    [$envioId] = envioConFotoEnAcme();
    entrarEnAcme('owner@acme.test');

    $url = urlFirmadaDeLaFicha($envioId);
    $sinFirma = strtok($url, '?');

    $this->get($sinFirma)->assertForbidden();
    $this->get(preg_replace('/signature=[a-f0-9]+/', 'signature='.str_repeat('0', 64), $url))->assertForbidden();

    // Cinco minutos de vida (config security.evidence.signed_url_ttl).
    $this->travel(6)->minutes();
    $this->get($url)->assertForbidden();
})->group('evidence');

it('no sirve la evidencia sin sesion aunque la URL este firmada', function (): void {
    // La URL reenviada por WhatsApp no le sirve a quien no ha entrado.
    [$envioId] = envioConFotoEnAcme();
    entrarEnAcme('owner@acme.test');
    $url = urlFirmadaDeLaFicha($envioId);

    $this->post('http://acme.ronda.test/logout');

    $this->get($url)->assertRedirect('http://acme.ronda.test/login');
})->group('evidence');

it('vuelve a comprobar la Policy al servir: una URL valida no sirve a quien no alcanza la sede', function (): void {
    [$envioId] = envioConFotoEnAcme();

    // Otra encargada, de otra sede.
    enAcmeEvidencia(function (): void {
        encargadaConEvidencia(Site::factory()->create(), 'otra@acme.test');
    });

    entrarEnAcme('owner@acme.test');
    $url = urlFirmadaDeLaFicha($envioId);

    entrarEnAcme('otra@acme.test');

    $this->get($url)->assertForbidden();
    $this->get('http://acme.ronda.test/envios/'.$envioId)->assertForbidden();
})->group('evidence');

it('rechaza en el tenant B una URL firmada de evidencia del tenant A', function (): void {
    // sec. 7.5: «URL firmada de evidencia del tenant A rechazada en el contexto
    // del tenant B».
    [$envioId] = envioConFotoEnAcme();
    entrarEnAcme('owner@acme.test');
    $url = urlFirmadaDeLaFicha($envioId);

    $beta = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Beta',
        slug: 'beta',
        domain: 'beta.ronda.test',
        ownerName: 'Duena de Beta',
        ownerEmail: 'owner@beta.test',
        ownerPassword: CLAVE_EVIDENCIA,
    ));

    // Beta tiene su propio envio con foto, con el MISMO id de adjunto. Asi el
    // binding encuentra algo y lo unico que puede frenar la peticion es la
    // firma, que lleva el dominio de acme.
    $adjuntoDeBeta = $beta->run(function (): int {
        $obligacion = obligacionConFoto();
        $autora = encargadaConEvidencia($obligacion->site, 'encargada@beta.test');
        entregarConEvidencia($obligacion, $autora, [
            'foto' => [new EvidenceUpload(EvidenceFixtures::jpeg(), 'beta.jpg')],
        ]);

        return Attachment::query()->sole()->id;
    });

    expect((string) $adjuntoDeBeta)->toBe((string) preg_replace('#^.*/evidencia/(\d+)\?.*$#', '$1', $url));

    // Sesion real en beta, con permiso para ver su propia evidencia.
    $this->post('http://acme.ronda.test/logout');
    $this->flushSession();
    $this->post('http://beta.ronda.test/login', ['email' => 'owner@beta.test', 'password' => CLAVE_EVIDENCIA]);
    $this->assertAuthenticated();

    // Ancla: en beta, beta SI ve su evidencia con su propia URL firmada.
    $this->get('http://beta.ronda.test/envios/1')->assertOk();

    $this->get(str_replace('acme.ronda.test', 'beta.ronda.test', $url))->assertForbidden();

    dropTenantDatabase($beta);
})->group('evidence');
