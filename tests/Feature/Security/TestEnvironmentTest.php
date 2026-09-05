<?php

declare(strict_types=1);

// La suite no puede tocar la base de desarrollo.
//
// docker-compose inyecta .env en el contenedor con `env_file`, asi que
// APP_ENV, DB_DATABASE y QUEUE_CONNECTION llegan como variables de entorno
// reales del proceso. PHPUnit no sobreescribe las que ya existen salvo que la
// entrada lleve force="true": sin el, phpunit.xml se ignora en silencio y las
// pruebas corren contra `ronda_central` con la cola de redis.
//
// No es teorico. Paso: un test con DatabaseMigrations ejecuto migrate:fresh
// sobre la base de desarrollo y la dejo vacia, con las bases de sus tenants
// huerfanas. Este test existe para que se detecte en la CI y no a mano.

it('no corre contra la base de desarrollo', function (): void {
    // Con --parallel, Pest da a cada proceso su propia base y le anade un
    // sufijo (`ronda_testing_test_3`), asi que se comprueba el prefijo.
    expect(config('database.connections.pgsql.database'))
        ->not->toBe('ronda_central', 'La suite apunta a la base de DESARROLLO. Revisa force="true" en phpunit.xml.')
        ->toStartWith('ronda_testing');
})->group('security');

it('corre en el entorno de pruebas', function (): void {
    expect(app()->environment())->toBe('testing');
})->group('security');

it('no usa servicios compartidos con el entorno de desarrollo', function (): void {
    // Una cola real haria que las pruebas dejasen trabajo pendiente en redis,
    // y una cache compartida filtra estado de una prueba a la siguiente.
    expect(config('queue.default'))->toBe('sync')
        ->and(config('cache.default'))->toBe('array')
        ->and(config('session.driver'))->toBe('array')
        ->and(config('mail.default'))->toBe('array');
})->group('security');
