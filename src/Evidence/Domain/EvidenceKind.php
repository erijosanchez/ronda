<?php

declare(strict_types=1);

namespace Ronda\Evidence\Domain;

use Ronda\Forms\Domain\ValueObjects\FieldType;

/**
 * Que clase de evidencia es un archivo. RONDA-PLAN-MAESTRO.md sec. 9.5
 *
 * Cada clase decide que contenido admite. La lista es blanca y se comprueba
 * contra el contenido REAL del archivo, no contra su extension ni contra lo que
 * declare el navegador (sec. 10.4): un `arqueo.jpg` que en realidad es un HTML
 * no entra.
 */
enum EvidenceKind: string
{
    case Photo = 'photo';
    case File = 'file';
    case Signature = 'signature';

    /**
     * La clase de evidencia que pide un tipo de campo, o null si el campo no
     * lleva archivos.
     */
    public static function forFieldType(FieldType $type): ?self
    {
        return match ($type) {
            FieldType::Photo => self::Photo,
            FieldType::File => self::File,
            FieldType::Signature => self::Signature,
            default => null,
        };
    }

    /**
     * Tipos MIME admitidos, detectados por contenido.
     *
     * Sin SVG en ningun caso: es XML con scripts dentro, y servirlo inline es
     * servir codigo.
     *
     * @return list<string>
     */
    public function allowedMimeTypes(): array
    {
        return match ($this) {
            self::Photo => ['image/jpeg', 'image/png', 'image/webp'],
            self::File => [
                'image/jpeg', 'image/png', 'image/webp',
                'application/pdf',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
            self::Signature => ['image/png'],
        };
    }

    /**
     * Cuantos archivos admite un mismo campo.
     */
    public function maxPerField(): int
    {
        return match ($this) {
            self::Photo, self::File => 5,
            self::Signature => 1,
        };
    }

    public function isImage(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/');
    }
}
