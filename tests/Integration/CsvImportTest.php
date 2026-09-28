<?php

declare(strict_types=1);

use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Ronda\Directory\Application\Actions\ImportSites;
use Ronda\Directory\Application\Data\PlannedSite;
use Ronda\Directory\Application\Data\SiteData;
use Ronda\Directory\Application\Data\SiteImportPlan;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Directory\Domain\Models\Zone;
use Ronda\Directory\Presentation\Livewire\SiteImport;
use Ronda\Identity\Application\Actions\ImportUsers;
use Ronda\Identity\Application\Data\PlannedUser;
use Ronda\Identity\Application\Data\UserData;
use Ronda\Identity\Application\Data\UserImportPlan;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\RoleName;
use Ronda\Identity\Domain\UserStatus;
use Ronda\Identity\Presentation\Livewire\UserImport;
use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Csv\ImportIssue;
use Ronda\Platform\Domain\Csv\ImportIssueReason;
use Ronda\Platform\Domain\Exceptions\InvalidImportPlan;
use Ronda\Platform\Domain\Models\Tenant;

// Importadores CSV de sedes y personas. RONDA-PLAN-MAESTRO.md sec. 13.2
//
// Lo que se prueba no es «lee un CSV». Es:
//
//   1. Que ANALIZAR no escribe nada, y que confirmar escribe todo o nada.
//   2. Que subir dos veces el mismo archivo no duplica.
//   3. Que el archivo que sale de Excel en espanol —punto y coma, BOM,
//      Windows-1252— entra igual que uno limpio.
//   4. Que importar no es una puerta de atras: ni al propietario, ni al rol de
//      propietario, ni a los codigos de lo que esta dado de baja.

const CLAVE_IMPORT = 'una-contrasena-larga-de-prueba';

beforeEach(function (): void {
    $this->tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Acme',
        slug: 'acme',
        domain: 'acme.ronda.test',
        ownerName: 'Duena de Acme',
        ownerEmail: 'owner@acme.test',
        ownerPassword: CLAVE_IMPORT,
    ));
});

afterEach(function (): void {
    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    dropTenantDatabase($tenant);
});

function enAcmeImport(Closure $fn): mixed
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;

    return $tenant->run($fn);
}

/** La duena, que es quien administra la estructura. */
function administradoraDeAcme(): User
{
    $duena = User::query()->where('email', 'owner@acme.test')->firstOrFail();
    auth()->login($duena);

    return $duena;
}

function archivoCsv(string $contenido, string $nombre = 'datos.csv'): File
{
    // `fake()->createWithContent` y no un UploadedFile a mano: el ayudante de
    // pruebas de Livewire lee `->name`, que solo tiene el falso.
    return UploadedFile::fake()->createWithContent($nombre, $contenido);
}

it('analiza sin escribir y despues importa', function (): void {
    enAcmeImport(function (): void {
        administradoraDeAcme();

        $csv = archivoCsv(<<<'CSV'
        codigo,nombre,zona_horaria
        LIM-001,Miraflores,America/Lima
        LIM-002,San Isidro,America/Lima
        CSV);

        $pantalla = Livewire::test(SiteImport::class)
            ->set('file', $csv)
            ->call('analizar')
            ->assertHasNoErrors()
            ->assertSet('toCreate', 2)
            ->assertSet('toUpdate', 0)
            ->assertSet('issues', []);

        // Analizar no escribe: es la mitad del valor de la pantalla.
        expect(Site::query()->count())->toBe(0);

        $pantalla->call('confirmar')->assertHasNoErrors();

        expect(Site::query()->pluck('code')->sort()->values()->all())
            ->toBe(['LIM-001', 'LIM-002'])
            ->and(Site::query()->where('code', 'LIM-001')->value('timezone'))
            ->toBe('America/Lima');
    });
})->group('import');

it('subir dos veces el mismo archivo actualiza, no duplica', function (): void {
    enAcmeImport(function (): void {
        administradoraDeAcme();

        $primera = archivoCsv("codigo;nombre\nLIM-001;Miraflores\n");

        Livewire::test(SiteImport::class)
            ->set('file', $primera)
            ->call('analizar')
            ->call('confirmar')
            ->assertHasNoErrors();

        // Alguien corrige una celda y reintenta: es lo normal.
        $segunda = archivoCsv("codigo;nombre\nLIM-001;Miraflores Centro\n");

        Livewire::test(SiteImport::class)
            ->set('file', $segunda)
            ->call('analizar')
            ->assertSet('toCreate', 0)
            ->assertSet('toUpdate', 1)
            ->call('confirmar');

        expect(Site::query()->count())->toBe(1)
            ->and(Site::query()->value('name'))->toBe('Miraflores Centro');
    });
})->group('import');

it('lee lo que guarda Excel en espanol: punto y coma, BOM y Windows-1252', function (): void {
    enAcmeImport(function (): void {
        administradoraDeAcme();

        // Tal cual sale de «Guardar como CSV» en un Windows en espanol.
        $contenido = "\u{FEFF}Código;Nombre;Dirección\r\n"
            .mb_convert_encoding("LIM-001;Ancón;Av. Perú 123\r\n", 'Windows-1252', 'UTF-8');

        Livewire::test(SiteImport::class)
            ->set('file', archivoCsv($contenido))
            ->call('analizar')
            ->assertSet('issues', [])
            ->assertSet('toCreate', 1)
            ->call('confirmar');

        $sede = Site::query()->firstOrFail();

        expect($sede->code)->toBe('LIM-001')
            ->and($sede->name)->toBe('Ancón')
            ->and($sede->address)->toBe('Av. Perú 123');
    });
})->group('import');

it('no escribe ni una fila mientras haya un problema', function (): void {
    enAcmeImport(function (): void {
        administradoraDeAcme();

        $csv = archivoCsv(<<<'CSV'
        codigo,nombre,zona
        LIM-001,Miraflores,
        LIM-002,,
        LIM-003,San Borja,Zona Fantasma
        LIM-001,Repetida,
        CSV);

        $pantalla = Livewire::test(SiteImport::class)
            ->set('file', $csv)
            ->call('analizar');

        $problemas = $pantalla->get('issues');

        expect($problemas)->toHaveCount(3)
            // La fila 3 del archivo, no «la segunda que fallo»: el usuario
            // busca en su hoja por numero de fila.
            ->and(array_column($problemas, 'row'))->toBe([3, 4, 5]);

        // Y confirmar a pesar de todo no escribe nada.
        $pantalla->call('confirmar');

        expect(Site::query()->count())->toBe(0);
    });
})->group('import');

it('rechaza el codigo de una sede dada de baja', function (): void {
    enAcmeImport(function (): void {
        administradoraDeAcme();

        Site::factory()->create(['code' => 'LIM-001', 'name' => 'La de antes'])->delete();

        Livewire::test(SiteImport::class)
            ->set('file', archivoCsv("codigo,nombre\nLIM-001,La nueva\n"))
            ->call('analizar')
            ->call('confirmar');

        // Un codigo de sede no se recicla: el indice unico de la base tampoco
        // lo permitiria, y fallar aqui con un mensaje es mejor que reventar
        // dentro del INSERT.
        expect(Site::query()->withTrashed()->count())->toBe(1);
    });
})->group('import');

it('dice que columnas faltan, todas de una vez', function (): void {
    enAcmeImport(function (): void {
        administradoraDeAcme();

        Livewire::test(SiteImport::class)
            ->set('file', archivoCsv("provincia,distrito\nLima,Miraflores\n"))
            ->call('analizar')
            ->assertHasErrors('file')
            ->assertSet('analyzed', false);
    });
})->group('import');

it('empareja la zona por su nombre, sin importar acentos ni mayusculas', function (): void {
    enAcmeImport(function (): void {
        administradoraDeAcme();

        $zona = Zone::create(['name' => 'Lima Norte']);

        Livewire::test(SiteImport::class)
            ->set('file', archivoCsv("codigo,nombre,zona\nLIM-001,Comas,LIMA  norte\n"))
            ->call('analizar')
            ->assertSet('issues', [])
            ->call('confirmar');

        expect(Site::query()->value('zone_id'))->toBe($zona->id);
    });
})->group('import');

it('importa personas con sus roles y sus sedes', function (): void {
    enAcmeImport(function (): void {
        administradoraDeAcme();

        $sede = Site::factory()->create(['code' => 'LIM-001']);

        $csv = archivoCsv(<<<'CSV'
        nombre,correo,roles,sedes
        Ana Quispe,ana@acme.test,Encargado,LIM-001
        Beto Rojas,beto@acme.test,supervisor|site_manager,
        CSV);

        Livewire::test(UserImport::class)
            ->set('file', $csv)
            ->call('analizar')
            ->assertSet('toCreate', 2)
            ->assertSet('issues', [])
            ->call('confirmar')
            ->assertHasNoErrors();

        $ana = User::query()->where('email', 'ana@acme.test')->firstOrFail();
        $beto = User::query()->where('email', 'beto@acme.test')->firstOrFail();

        expect($ana->sites()->pluck('sites.id')->all())->toBe([$sede->id])
            // El rol se acepta por su nombre visible, que es lo que copia
            // quien llena la hoja mirando la pantalla.
            ->and($ana->getRoleNames()->all())->toBe([RoleName::SiteManager->value])
            ->and($beto->getRoleNames()->sort()->values()->all())
            ->toBe([RoleName::SiteManager->value, RoleName::Supervisor->value])
            ->and($beto->status)->toBe(UserStatus::Active);
    });
})->group('import');

it('nadie trae contrasena en el archivo', function (): void {
    enAcmeImport(function (): void {
        administradoraDeAcme();

        Livewire::test(UserImport::class)
            ->set('file', archivoCsv("nombre,correo,contrasena\nAna,ana@acme.test,hola1234\n"))
            ->call('analizar')
            ->call('confirmar');

        $ana = User::query()->where('email', 'ana@acme.test')->firstOrFail();

        // La columna se ignora: la contrasena es aleatoria y nadie la sabe.
        // Se entra por «olvide mi contrasena».
        expect(Hash::check('hola1234', $ana->password))->toBeFalse();
    });
})->group('import');

it('no le cambia la contrasena a quien ya existe', function (): void {
    enAcmeImport(function (): void {
        administradoraDeAcme();

        $ana = User::create([
            'name' => 'Ana',
            'email' => 'ana@acme.test',
            'password' => CLAVE_IMPORT,
        ]);
        $ana->assignRole(RoleName::SiteManager->value);

        Livewire::test(UserImport::class)
            ->set('file', archivoCsv("nombre,correo,roles\nAna Quispe,ana@acme.test,Supervisor\n"))
            ->call('analizar')
            ->assertSet('toUpdate', 1)
            ->call('confirmar');

        $ana->refresh();

        expect($ana->name)->toBe('Ana Quispe')
            ->and($ana->getRoleNames()->all())->toBe([RoleName::Supervisor->value])
            // Importar corrige datos; no echa a nadie de su sesion.
            ->and(Hash::check(CLAVE_IMPORT, $ana->password))->toBeTrue();
    });
})->group('import');

it('no toca al propietario ni deja conceder su rol', function (): void {
    enAcmeImport(function (): void {
        administradoraDeAcme();

        $csv = archivoCsv(<<<'CSV'
        nombre,correo,roles
        Otro Nombre,owner@acme.test,Administrador
        Ana Quispe,ana@acme.test,Propietario
        CSV);

        $pantalla = Livewire::test(UserImport::class)
            ->set('file', $csv)
            ->call('analizar');

        expect($pantalla->get('issues'))->toHaveCount(2)
            ->and($pantalla->get('toCreate'))->toBe(0);

        $pantalla->call('confirmar');

        $duena = User::query()->where('email', 'owner@acme.test')->firstOrFail();

        // UserPolicy::update solo deja que el propietario se edite a si mismo,
        // justamente para que un administrador no pueda quedarse con la
        // cuenta. Un CSV no puede ser la puerta de atras de esa regla.
        expect($duena->name)->toBe('Duena de Acme')
            ->and($duena->getRoleNames()->all())->toBe([RoleName::Owner->value])
            ->and(User::query()->where('email', 'ana@acme.test')->exists())->toBeFalse();
    });
})->group('import');

it('una celda de sedes vacia no le quita las sedes a nadie', function (): void {
    enAcmeImport(function (): void {
        administradoraDeAcme();

        $sede = Site::factory()->create(['code' => 'LIM-001']);

        $ana = User::create(['name' => 'Ana', 'email' => 'ana@acme.test', 'password' => CLAVE_IMPORT]);
        $ana->assignRole(RoleName::SiteManager->value);
        $ana->sites()->attach($sede->id);

        Livewire::test(UserImport::class)
            ->set('file', archivoCsv("nombre,correo,roles,sedes\nAna,ana@acme.test,Encargado,\n"))
            ->call('analizar')
            ->call('confirmar');

        // Un archivo al que le falta un dato no puede dejar a nadie sin acceso.
        expect($ana->fresh()?->sites()->count())->toBe(1);
    });
})->group('import');

it('avisa de la sede que no existe en vez de crearla por su cuenta', function (): void {
    enAcmeImport(function (): void {
        administradoraDeAcme();

        $pantalla = Livewire::test(UserImport::class)
            ->set('file', archivoCsv("nombre,correo,roles,sedes\nAna,ana@acme.test,Encargado,LIM-999\n"))
            ->call('analizar');

        expect($pantalla->get('issues'))->toHaveCount(1)
            ->and($pantalla->get('issues')[0]['message'])->toContain('LIM-999');

        expect(Site::query()->count())->toBe(0);
    });
})->group('import');

it('corta los archivos demasiado grandes en vez de intentarlo', function (): void {
    enAcmeImport(function (): void {
        administradoraDeAcme();

        $lineas = ['codigo,nombre'];

        for ($i = 1; $i <= 2001; $i++) {
            $lineas[] = "LIM-{$i},Sede {$i}";
        }

        Livewire::test(SiteImport::class)
            ->set('file', archivoCsv(implode("\n", $lineas)))
            ->call('analizar')
            ->assertHasErrors('file');

        expect(Site::query()->count())->toBe(0);
    });
})->group('import');

it('la Action se niega a escribir un plan con problemas, sin pasar por la pantalla', function (): void {
    // La pantalla ya no ofrece el boton cuando hay problemas, pero la guarda
    // tiene que estar TAMBIEN en la Action: manana la llamara la API, y una
    // comprobacion que vive solo en un formulario protege a un formulario.
    enAcmeImport(function (): void {
        administradoraDeAcme();

        $planSedes = new SiteImportPlan(
            [new PlannedSite(new SiteData(code: 'LIM-001', name: 'Miraflores', timezone: 'America/Lima'))],
            [new ImportIssue(2, 'codigo', ImportIssueReason::Required)],
        );

        expect(fn () => resolve(ImportSites::class)($planSedes))
            ->toThrow(InvalidImportPlan::class);

        $planPersonas = new UserImportPlan(
            [new PlannedUser(new UserData(
                name: 'Ana',
                email: 'ana@acme.test',
                status: UserStatus::Active,
                roles: [RoleName::SiteManager],
            ))],
            [new ImportIssue(2, 'correo', ImportIssueReason::Invalid, 'no-es-correo')],
        );

        expect(fn () => resolve(ImportUsers::class)($planPersonas))
            ->toThrow(InvalidImportPlan::class);

        expect(Site::query()->count())->toBe(0)
            ->and(User::query()->where('email', 'ana@acme.test')->exists())->toBeFalse();
    });
})->group('import');

it('quien no administra no entra a importar', function (): void {
    enAcmeImport(function (): void {
        $encargada = User::create([
            'name' => 'Encargada',
            'email' => 'encargada@acme.test',
            'password' => CLAVE_IMPORT,
        ]);
        $encargada->assignRole(RoleName::SiteManager->value);
        auth()->login($encargada->fresh());

        Livewire::test(SiteImport::class)->assertForbidden();
        Livewire::test(UserImport::class)->assertForbidden();
    });
})->group('import');

it('la plantilla trae las cabeceras que el importador espera', function (): void {
    enAcmeImport(function (): void {
        administradoraDeAcme();

        // La zona de la fila de ejemplo tiene que existir: la plantilla ensena
        // el formato, y las zonas se crean antes de importar sedes.
        Zone::create(['name' => 'Lima Centro']);

        // Se descarga, se llena y se vuelve a subir sin tocar la cabecera: ese
        // viaje tiene que funcionar, o la plantilla estorba en vez de ayudar.
        $pantalla = Livewire::test(SiteImport::class)
            ->call('plantilla')
            ->assertFileDownloaded('plantilla-sedes.csv');

        $plantilla = base64_decode((string) data_get($pantalla->effects, 'download.content'), true);

        expect($plantilla)->toBeString();

        Livewire::test(SiteImport::class)
            ->set('file', archivoCsv((string) $plantilla, 'plantilla-sedes.csv'))
            ->call('analizar')
            ->assertSet('issues', [])
            ->assertSet('toCreate', 1);
    });
})->group('import');
