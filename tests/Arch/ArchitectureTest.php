<?php

declare(strict_types=1);

// Las reglas de codigo no negociables del RONDA-PLAN-MAESTRO.md sec. 6.3.
// No son una guia de estilo: si una se rompe, la CI para el merge.
// La deuda de reports-trimax nacio de tener estas reglas escritas y no
// verificadas.

/**
 * Los once modulos del proyecto (CLAUDE.md). Las listas de exclusion se
 * derivan de aqui en vez de escribirse a mano: la version anterior obligaba a
 * anadir una linea cada vez que un modulo estrenaba una carpeta, y con once
 * modulos por delante eso es una edicion recurrente que ademas se olvida.
 *
 * @var list<string>
 */
$modules = [
    'Platform', 'Identity', 'Directory', 'Forms', 'Workflow', 'Scheduling',
    'Submissions', 'Evidence', 'Notifications', 'Insights', 'Api',
];

/**
 * @param  list<string>  $suffixes
 * @return list<string>
 */
$inEveryModule = static function (array $suffixes) use ($modules): array {
    $namespaces = [];

    foreach ($modules as $module) {
        foreach ($suffixes as $suffix) {
            $namespaces[] = 'Ronda\\'.$module.'\\'.$suffix;
        }
    }

    return $namespaces;
};

arch('todo el codigo declara tipos estrictos')
    ->expect('Ronda')
    ->toUseStrictTypes()
    ->group('arch');

arch('el dominio no conoce Eloquent, HTTP ni facades')
    ->expect('Ronda')
    ->not->toUse([
        'Illuminate\Support\Facades',
        'Illuminate\Http\Request',
        'Illuminate\Http\Response',
    ])
    // Presentation es la capa que habla HTTP: es su trabajo.
    ->ignoring($inEveryModule(['Presentation']))
    ->group('arch');

arch('la comprobacion de rol vive solo en Policies')
    ->expect(['hasRole', 'hasAnyRole', 'hasPermissionTo'])
    ->not->toBeUsedIn('Ronda')
    ->ignoring($inEveryModule(['Application\Policies']))
    ->group('arch');

arch('sin funciones de depuracion olvidadas')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die', 'exit'])
    ->not->toBeUsed()
    ->group('arch');

arch('las clases son finales')
    ->expect('Ronda')
    ->classes()
    ->toBeFinal()
    // Los estados declarativos tienen una clase base abstracta de la que
    // heredan los estados concretos (ADR 0007).
    ->ignoring($inEveryModule(['Domain\States']))
    ->group('arch');

arch('los DTOs son inmutables')
    ->expect('Ronda')
    ->classes()
    ->toBeReadonly()
    /*
     | Denegar por defecto, igual que RouteProtectionTest: todo se exige
     | readonly y se excluye lo que NO puede serlo, en vez de comprobar solo
     | `Application\Data` y arriesgarse a que un DTO nuevo se cuele sin
     | verificar.
     |
     | Lo excluido no es una decision de estilo: PHP prohibe que una clase
     | readonly extienda una que no lo es, y todo esto hereda del framework.
     |
     |   Domain           modelos de Eloquent
     |   Infrastructure   escuchadores y repositorios
     |   Presentation     proveedores y componentes Livewire
     |   Database         migraciones, factories y seeders
     |   Application\Actions/Jobs/Queries   jobs encolables y Actions con estado
     |
     | Lo que queda dentro de la regla es `Application\Data`, que es su
     | intencion: los DTOs.
     */
    ->ignoring($inEveryModule([
        'Domain',
        'Infrastructure',
        'Presentation',
        'Database',
        'Application\Actions',
        'Application\Jobs',
        'Application\Queries',
    ]))
    ->group('arch');

arch('preset de seguridad de Pest')
    ->preset()
    ->security()
    ->group('arch');
