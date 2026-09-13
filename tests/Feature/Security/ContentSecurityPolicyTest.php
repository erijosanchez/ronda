<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\Response as LaravelResponse;
use Ronda\Platform\Presentation\Http\Middleware\SecurityHeaders;
use Symfony\Component\HttpFoundation\Response;

// La CSP no puede relajarse por accidente. RONDA-PLAN-MAESTRO.md sec. 10.5
//
// Se ejercita el middleware directamente en vez de pedir una pagina: asi la
// prueba vive en la suite Feature y no necesita un tenant provisionado.

/**
 * @return array<string, string> directiva => valor
 */
function politica(): array
{
    $middleware = new SecurityHeaders;

    $respuesta = $middleware->handle(
        Request::create('https://acme.ronda.test/panel'),
        fn (): Response => new LaravelResponse('ok'),
    );

    $cabecera = (string) $respuesta->headers->get('Content-Security-Policy');

    $directivas = [];
    foreach (explode(';', $cabecera) as $trozo) {
        $trozo = trim($trozo);
        if ($trozo === '') {
            continue;
        }
        [$nombre] = explode(' ', $trozo, 2);
        $directivas[$nombre] = $trozo;
    }

    return $directivas;
}

it('nunca permite unsafe-inline', function (): void {
    // Es la unica directiva que de verdad convierte un XSS en ejecucion de
    // codigo. Si alguien la anade para «que funcione algo», esta prueba para
    // el merge.
    $politica = politica();

    expect($politica['script-src'])->not->toContain("'unsafe-inline'")
        ->and($politica['style-src'])->not->toContain("'unsafe-inline'");
})->group('security');

it('exige un nonce por peticion en scripts y estilos', function (): void {
    $politica = politica();

    expect($politica['script-src'])->toMatch("/'nonce-[A-Za-z0-9+\/=]+'/")
        ->and($politica['style-src'])->toMatch("/'nonce-[A-Za-z0-9+\/=]+'/");
})->group('security');

it('genera un nonce distinto en cada peticion', function (): void {
    // Un nonce fijo no sirve de nada: el atacante lo copia del HTML anterior.
    $extraer = static fn (array $p): string => (string) preg_replace(
        "/.*'nonce-([^']+)'.*/", '$1', $p['script-src'],
    );

    expect($extraer(politica()))->not->toBe($extraer(politica()));
})->group('security');

it('permite unsafe-eval, que necesita el evaluador de Alpine', function (): void {
    // Esto NO es un descuido: sin ello ninguna directiva de Alpine se ejecuta,
    // porque Livewire empaqueta un Alpine que usa `new Function`. El build
    // CSP-safe lo evitaria, pero Flux no funciona con el.
    // Ver docs/adr/0011-csp-y-unsafe-eval.md antes de quitarlo.
    expect(politica()['script-src'])->toContain("'unsafe-eval'");
})->group('security');

it('mantiene cerradas las demas directivas', function (): void {
    $politica = politica();

    expect($politica['default-src'])->toBe("default-src 'self'")
        ->and($politica['object-src'])->toBe("object-src 'none'")
        ->and($politica['frame-ancestors'])->toBe("frame-ancestors 'none'")
        ->and($politica['base-uri'])->toBe("base-uri 'self'")
        ->and($politica['form-action'])->toBe("form-action 'self'");
})->group('security');
