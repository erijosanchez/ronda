<?php

declare(strict_types=1);

// Las reglas de codigo no negociables del RONDA-PLAN-MAESTRO.md sec. 6.3.
// No son una guia de estilo: si una se rompe, la CI para el merge.
// La deuda de reports-trimax nacio de tener estas reglas escritas y no
// verificadas.

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
    ->ignoring([
        'Ronda\Platform\Presentation',
        'Ronda\Identity\Presentation',
        'Ronda\Directory\Presentation',
        'Ronda\Forms\Presentation',
        'Ronda\Workflow\Presentation',
        'Ronda\Scheduling\Presentation',
        'Ronda\Submissions\Presentation',
        'Ronda\Evidence\Presentation',
        'Ronda\Notifications\Presentation',
        'Ronda\Insights\Presentation',
        'Ronda\Api\Presentation',
    ])
    ->group('arch');

arch('la comprobacion de rol vive solo en Policies')
    ->expect(['hasRole', 'hasAnyRole', 'hasPermissionTo'])
    ->not->toBeUsedIn('Ronda')
    ->ignoring([
        'Ronda\Platform\Application\Policies',
        'Ronda\Identity\Application\Policies',
        'Ronda\Directory\Application\Policies',
        'Ronda\Forms\Application\Policies',
        'Ronda\Workflow\Application\Policies',
        'Ronda\Scheduling\Application\Policies',
        'Ronda\Submissions\Application\Policies',
        'Ronda\Evidence\Application\Policies',
        'Ronda\Notifications\Application\Policies',
        'Ronda\Insights\Application\Policies',
        'Ronda\Api\Application\Policies',
    ])
    ->group('arch');

arch('sin funciones de depuracion olvidadas')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die', 'exit'])
    ->not->toBeUsed()
    ->group('arch');

arch('las Actions son finales e invocables')
    ->expect('Ronda')
    ->classes()
    ->toBeFinal()
    ->ignoring(['Ronda\Platform\Domain\States', 'Ronda\Submissions\Domain\States'])
    ->group('arch');

arch('los DTOs son inmutables')
    ->expect('Ronda')
    ->classes()
    ->toBeReadonly()
    ->ignoring([
        'Ronda\Platform\Domain',
        'Ronda\Identity\Domain',
        'Ronda\Directory\Domain',
        'Ronda\Forms\Domain',
        'Ronda\Workflow\Domain',
        'Ronda\Scheduling\Domain',
        'Ronda\Submissions\Domain',
        'Ronda\Evidence\Domain',
        'Ronda\Notifications\Domain',
        'Ronda\Insights\Domain',
        'Ronda\Api\Domain',
        'Ronda\Platform\Application\Actions',
        'Ronda\Identity\Application\Actions',
        'Ronda\Directory\Application\Actions',
        'Ronda\Forms\Application\Actions',
        'Ronda\Workflow\Application\Actions',
        'Ronda\Scheduling\Application\Actions',
        'Ronda\Submissions\Application\Actions',
        'Ronda\Evidence\Application\Actions',
        'Ronda\Notifications\Application\Actions',
        'Ronda\Insights\Application\Actions',
        'Ronda\Api\Application\Actions',
        'Ronda\Platform\Application\Jobs',
        'Ronda\Platform\Application\Queries',
        'Ronda\Platform\Infrastructure',
        'Ronda\Identity\Infrastructure',
        'Ronda\Platform\Presentation',
        'Ronda\Identity\Presentation',
    ])
    ->group('arch');

arch('nada de codigo de depuracion de Laravel en produccion')
    ->expect('Laravel\Telescope')
    ->not->toBeUsedIn('Ronda')
    ->group('arch');

arch('preset de seguridad de Pest')
    ->preset()
    ->security()
    ->group('arch');
