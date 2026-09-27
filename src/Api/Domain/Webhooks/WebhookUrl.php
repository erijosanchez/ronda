<?php

declare(strict_types=1);

namespace Ronda\Api\Domain\Webhooks;

use Ronda\Api\Domain\Exceptions\UnsafeWebhookUrl;

/**
 * Una direccion a la que Ronda acepta mandar avisos.
 * RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * Es el guardia contra SSRF, y el motivo es concreto: un webhook es una URL
 * que ESCRIBE UN CLIENTE y a la que llama NUESTRO servidor. Sin filtro, alguien
 * pone `http://169.254.169.254/latest/meta-data/` y Ronda le entrega las
 * credenciales de la nube. O apunta a `http://postgres:5432` y usa nuestro
 * servidor para escanear la red interna.
 *
 * Por eso:
 *
 *   - Solo `https` (en local tambien `http`, para poder probar).
 *   - Nada de direcciones privadas, de bucle ni de enlace local, ni escritas a
 *     mano ni escondidas detras de un nombre que resuelve ahi.
 *   - Se comprueba la IP a la que resuelve el nombre, no solo el texto.
 *
 * Quien envia, ademas, no sigue redirecciones: una URL publica que redirige a
 * `127.0.0.1` se saltaria todo esto.
 */
final readonly class WebhookUrl
{
    private function __construct(public string $value) {}

    /**
     * @throws UnsafeWebhookUrl
     */
    public static function fromString(string $url, bool $allowInsecure = false): self
    {
        $limpia = trim($url);
        $partes = parse_url($limpia);

        if ($partes === false || ! isset($partes['scheme'], $partes['host'])) {
            throw UnsafeWebhookUrl::malformed($limpia);
        }

        $esquema = mb_strtolower($partes['scheme']);
        $permitidos = $allowInsecure ? ['http', 'https'] : ['https'];

        if (! in_array($esquema, $permitidos, true)) {
            throw UnsafeWebhookUrl::insecureScheme($esquema);
        }

        self::guardHost($partes['host']);

        return new self($limpia);
    }

    /**
     * @throws UnsafeWebhookUrl
     */
    private static function guardHost(string $host): void
    {
        // Un nombre corto sin punto (`postgres`, `redis`, `localhost`) solo
        // resuelve dentro de nuestra red: no puede ser el destino de nadie.
        if (! str_contains($host, '.') && ! str_contains($host, ':')) {
            throw UnsafeWebhookUrl::privateHost($host);
        }

        foreach (self::resolve($host) as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw UnsafeWebhookUrl::privateHost($host);
            }
        }
    }

    /**
     * Las IPs a las que resuelve el nombre.
     *
     * Si es ya una IP, esa. Si no resuelve, se deja pasar: un DNS que todavia
     * no propaga no es un ataque, y quien envia vuelve a comprobar el destino
     * en el momento de llamar.
     *
     * @return list<string>
     */
    private static function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $registros = @dns_get_record($host, DNS_A | DNS_AAAA);

        if ($registros === false || $registros === []) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (array $registro): ?string => $registro['ip'] ?? $registro['ipv6'] ?? null,
            $registros,
        )));
    }
}
