<?php

declare(strict_types=1);

namespace Ronda\Forms\Domain\ValueObjects;

/**
 * Tipos de campo de la v1. RONDA-PLAN-MAESTRO.md sec. 9.2
 *
 * El nombre visible sale de `label()`: codigo en ingles, interfaz en espanol.
 */
enum FieldType: string
{
    case Text = 'text';

    case Number = 'number';

    /** Importe. Se guarda en numeric(14,2) con la moneda aparte, nunca float. */
    case Money = 'money';

    case Date = 'date';

    case Time = 'time';

    case Boolean = 'boolean';

    case Select = 'select';

    case MultiSelect = 'multi_select';

    /** Foto, con geoetiqueta opcionalmente obligatoria (sec. 9.5). */
    case Photo = 'photo';

    case File = 'file';

    case Signature = 'signature';

    /** Tabla de filas repetibles, para el detalle de un arqueo. */
    case Table = 'table';

    /** Formula sobre otros campos. No lo rellena el usuario. */
    case Calculated = 'calculated';

    /** Texto fijo. Ni se responde ni se guarda. */
    case Section = 'section';

    public function label(): string
    {
        return __('field-types.'.$this->value);
    }

    /**
     * Si el campo admite una lista de opciones.
     */
    public function hasOptions(): bool
    {
        return match ($this) {
            self::Select, self::MultiSelect => true,
            default => false,
        };
    }

    /**
     * Si el usuario introduce un valor.
     *
     * Una seccion es texto fijo y un campo calculado lo deriva el motor: ni uno
     * ni otro se responden, asi que tampoco pueden ser obligatorios.
     */
    public function isAnswerable(): bool
    {
        return match ($this) {
            self::Section, self::Calculated => false,
            default => true,
        };
    }

    /**
     * Si el valor puede replicarse en `submission_values` para KPI y filtros.
     *
     * Un archivo, una firma o una tabla de filas no tienen un valor escalar que
     * indexar; su sitio es el JSONB y el almacen de evidencia (ADR 0012).
     */
    public function isReportable(): bool
    {
        return match ($this) {
            self::Photo, self::File, self::Signature, self::Table, self::Section => false,
            default => true,
        };
    }
}
