<?php

declare(strict_types=1);

namespace Ronda\Forms\Domain\ValueObjects;

/**
 * Operadores de una condicion de visibilidad. Ver VisibilityCondition.
 */
enum ComparisonOperator: string
{
    case Equals = 'equals';

    case NotEquals = 'not_equals';

    case In = 'in';

    case GreaterThan = 'greater_than';

    case LessThan = 'less_than';

    case IsEmpty = 'is_empty';

    case IsNotEmpty = 'is_not_empty';

    /**
     * Si el operador necesita un valor con el que comparar.
     *
     * `is_empty` no lo necesita, y exigirselo obligaria al disenador a rellenar
     * un campo que no significa nada.
     */
    public function needsValue(): bool
    {
        return match ($this) {
            self::IsEmpty, self::IsNotEmpty => false,
            default => true,
        };
    }
}
