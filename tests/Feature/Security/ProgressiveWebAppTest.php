<?php

declare(strict_types=1);

// La PWA. RONDA-PLAN-MAESTRO.md sec. 13.3 y ADR 0010.
//
// El comportamiento sin señal vive en el navegador y no se puede probar desde
// PHP. Lo que SI se puede sostener aqui es el contrato: que las piezas existen,
// que dicen lo que tienen que decir, y que el service worker no guarda en el
// telefono nada privado.
//
// Lo que estas pruebas NO cubren, y hay que mirar a mano en un movil: que el
// navegador ofrezca instalar, que el borrador vuelva tras cerrar la pestana y
// que la pantalla vieja se sirva sin red.

function publico(string $archivo): string
{
    $ruta = public_path($archivo);

    expect(file_exists($ruta))->toBeTrue("Falta {$archivo} en public/");

    return (string) file_get_contents($ruta);
}

it('publica un manifiesto instalable, con iconos que existen', function (): void {
    /** @var array<string, mixed> $manifiesto */
    $manifiesto = json_decode(publico('manifest.webmanifest'), true, flags: JSON_THROW_ON_ERROR);

    expect($manifiesto['display'])->toBe('standalone')
        // Arranca en los pendientes, que es a lo que entra el encargado.
        ->and($manifiesto['start_url'])->toBe('/pendientes')
        ->and($manifiesto['scope'])->toBe('/')
        ->and($manifiesto['icons'])->not->toBeEmpty();

    /** @var list<array{src: string, purpose?: string}> $iconos */
    $iconos = $manifiesto['icons'];

    foreach ($iconos as $icono) {
        expect(file_exists(public_path(ltrim($icono['src'], '/'))))->toBeTrue("Falta el icono {$icono['src']}");
    }

    // Android recorta el icono: hace falta uno pensado para eso.
    $propositos = array_map(static fn (array $icono): string => $icono['purpose'] ?? 'any', $iconos);

    expect($propositos)->toContain('maskable');
})->group('pwa');

it('tiene una pagina de sin conexion que se explica sola', function (): void {
    $pagina = publico('offline.html');

    // Sin red no hay bundle ni servidor: todo tiene que venir dentro.
    expect($pagina)->toContain('Sin conexión')
        ->and($pagina)->toContain('guardado en este teléfono')
        ->and($pagina)->not->toContain('@vite')
        ->and($pagina)->not->toContain('/build/');
})->group('pwa');

it('el service worker no guarda en el telefono nada privado', function (): void {
    $worker = publico('sw.js');

    // La evidencia es privada y firmada; una exportacion, datos del cliente; el
    // endpoint de Livewire, respuestas a medias. Nada de eso puede quedarse en
    // la cache de un telefono que se comparte.
    // Se comprueban los patrones tal como estan escritos en la lista `NUNCA`.
    expect($worker)->toContain('/^\/evidencia\//')
        ->and($worker)->toContain('/^\/exportaciones\/')
        ->and($worker)->toContain('/^\/livewire\//')
        // Solo GET: un POST guardado seria una entrega fantasma.
        ->and($worker)->toContain("request.method !== 'GET'")
        // Al cerrar sesion se tiran las pantallas guardadas.
        ->and($worker)->toContain('clear-pages')
        ->and($worker)->toContain('/offline.html');
})->group('pwa');

it('el layout ofrece instalar la aplicacion y registrar el service worker', function (): void {
    $layout = (string) file_get_contents(resource_path('views/components/layouts/app.blade.php'));
    $registro = (string) file_get_contents(resource_path('js/pwa.js'));

    // Tambien en el login: es la primera pantalla que ve el encargado y desde
    // donde va a instalar la aplicacion.
    $invitado = (string) file_get_contents(resource_path('views/components/layouts/guest.blade.php'));

    expect($invitado)->toContain('rel="manifest"');

    expect($layout)->toContain('rel="manifest"')
        ->toContain('/icons/apple-touch-icon.png')
        ->toContain('theme-color')
        // El aviso de sin conexion, antes de que alguien intente entregar.
        ->toContain('connectionStatus');

    expect($registro)->toContain("navigator.serviceWorker.register('/sw.js')")
        // En http:// sin localhost el navegador lo rechaza; no se intenta.
        ->toContain('window.isSecureContext');
})->group('pwa');

it('el formulario de entrega y el de correccion guardan borrador en el dispositivo', function (): void {
    $entrega = (string) file_get_contents(base_path('src/Submissions/Presentation/Views/form.blade.php'));
    $correccion = (string) file_get_contents(base_path('src/Workflow/Presentation/Views/correction.blade.php'));
    $borradores = (string) file_get_contents(resource_path('js/pwa.js'));

    expect($entrega)->toContain('submissionDraft(')
        ->and($correccion)->toContain('submissionDraft(');

    // En IndexedDB, como manda la sec. 13.3, y se borra cuando el envio ya
    // esta guardado en el servidor.
    expect($borradores)->toContain('indexedDB.open')
        ->toContain("window.addEventListener('submission-saved'");
})->group('pwa');

it('la CSP deja funcionar al service worker y al manifiesto', function (): void {
    // Si la CSP no los permite, la PWA deja de instalarse sin que nadie se
    // entere hasta que un encargado se queda sin su reporte.
    $respuesta = $this->get('http://localhost/');

    $csp = (string) $respuesta->headers->get('Content-Security-Policy');

    expect($csp)->toContain("worker-src 'self'")
        ->toContain("manifest-src 'self'");
})->group('pwa');
