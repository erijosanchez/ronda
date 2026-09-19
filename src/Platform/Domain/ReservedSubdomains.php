<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain;

/**
 * Subdominios que ningun cliente puede tomar. RONDA-PLAN-MAESTRO.md sec. 15.3
 *
 * Unos son infraestructura (`www`, `api`, `mail`) y otros son nuestros
 * (`app`, `admin`, `soporte`): si un cliente se queda con uno, o rompe algo que
 * ya existe, o se hace pasar por Ronda ante sus propios usuarios.
 *
 * Es una lista cerrada y corta a proposito: lo que no esta aqui se puede
 * registrar, y anadir uno mas cuesta una linea.
 */
final readonly class ReservedSubdomains
{
    /** @var list<string> */
    private const array RESERVED = [
        'www', 'api', 'app', 'admin', 'administrador', 'soporte', 'support',
        'mail', 'correo', 'smtp', 'imap', 'ftp', 'cdn', 'static', 'assets',
        'status', 'estado', 'blog', 'docs', 'ayuda', 'help', 'login', 'panel',
        'dashboard', 'billing', 'facturacion', 'pagos', 'checkout', 'ronda',
        'demo', 'test', 'staging', 'dev', 'localhost', 'horizon', 'metrics',
    ];

    public static function taken(string $subdomain): bool
    {
        return in_array(mb_strtolower(trim($subdomain)), self::RESERVED, true);
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return self::RESERVED;
    }
}
