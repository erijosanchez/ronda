<?php

declare(strict_types=1);

namespace Ronda\Platform\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad HTTP, incluida una CSP estricta.
 *
 * La CSP es estricta desde el primer despliegue, no como correccion posterior.
 * Solo es posible porque no hay JavaScript en linea en Blade ni librerias
 * cargadas desde CDN: en reports-trimax una CSP asi habria roto 52 vistas.
 *
 * Ver RONDA-PLAN-MAESTRO.md sec. 10.5
 */
final class SecurityHeaders
{
    /**
     * Cabeceras fijas, independientes de la peticion.
     *
     * @var array<string, string>
     */
    private const array HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'geolocation=(self), camera=(self), microphone=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
        'Cross-Origin-Resource-Policy' => 'same-origin',
        'X-Permitted-Cross-Domain-Policies' => 'none',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! config('security.headers_enabled', true)) {
            return $response;
        }

        foreach (self::HEADERS as $header => $value) {
            $response->headers->set($header, $value);
        }

        // HSTS solo bajo HTTPS real: enviarlo por HTTP no hace nada y confunde
        // al depurar.
        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=63072000; includeSubDomains; preload',
            );
        }

        if (config('security.csp_enabled', true)) {
            $response->headers->set(
                'Content-Security-Policy',
                $this->contentSecurityPolicy($request),
            );
        }

        return $response;
    }

    private function contentSecurityPolicy(Request $request): string
    {
        $nonce = $this->nonce($request);
        $websocket = $this->websocketOrigin();

        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'nonce-{$nonce}'",
            "img-src 'self' data: blob:",
            "font-src 'self'",
            "connect-src 'self' {$websocket}",
            "media-src 'self' blob:",
            "worker-src 'self' blob:",
            "manifest-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
            'upgrade-insecure-requests',
        ];

        return implode('; ', $directives);
    }

    /**
     * Un nonce por peticion, compartido con las vistas.
     */
    private function nonce(Request $request): string
    {
        /** @var string|null $existing */
        $existing = $request->attributes->get('csp_nonce');

        if (is_string($existing)) {
            return $existing;
        }

        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);

        return $nonce;
    }

    private function websocketOrigin(): string
    {
        $scheme = config('reverb.apps.apps.0.options.scheme', 'http') === 'https' ? 'wss' : 'ws';
        $host = (string) config('reverb.apps.apps.0.options.host', 'localhost');
        $port = (string) config('reverb.apps.apps.0.options.port', '8080');

        return "{$scheme}://{$host}:{$port}";
    }
}
