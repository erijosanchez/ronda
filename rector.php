<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Transform\Rector\String_\StringToClassConstantRector;
use RectorLaravel\Set\LaravelLevelSetList;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/src',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withSkip([
        __DIR__.'/bootstrap/cache',
        __DIR__.'/storage',
        __DIR__.'/vendor',

        // Los tests de arquitectura comparan PREFIJOS de namespace como texto
        // ('Ronda\Platform\Presentation' no es una clase). Convertirlos a
        // ::class es ruido y en algunos casos ni siquiera compila.
        StringClassNameToClassConstantRector::class => [
            __DIR__.'/tests/Arch',
        ],

        // Traduce nombres de eventos por cadena de Laravel 5 a ::class, y su
        // tabla incluye 'auth.login' -> Illuminate\Auth\Events\Login. Eso
        // colisiona con el nombre de nuestra vista Blade `auth.login`:
        // convertia view('auth.login') en view(Login::class) y volvia a romper
        // el login. Este proyecto nace en Laravel 12 y nunca uso eventos por
        // cadena, asi que la regla no tiene nada legitimo que arreglar aqui.
        StringToClassConstantRector::class,

        // Las Policies reciben el modelo aunque una comprobacion concreta no
        // lo mire: es el contrato que invoca Laravel y lo que necesitara
        // cualquier refinamiento de la regla. Quitarlo deja una firma que
        // miente sobre lo que la Policy decide.
        RemoveUnusedPublicMethodParameterRector::class => [
            __DIR__.'/src/*/Application/Policies/*',
        ],
    ])
    ->withSets([
        LevelSetList::UP_TO_PHP_84,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
        SetList::EARLY_RETURN,
        LaravelLevelSetList::UP_TO_LARAVEL_120,
        LaravelSetList::LARAVEL_CODE_QUALITY,
    ])
    ->withImportNames(importShortClasses: false)
    ->withPhpSets(php84: true);
