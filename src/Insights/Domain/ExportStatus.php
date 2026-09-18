<?php

declare(strict_types=1);

namespace Ronda\Insights\Domain;

/**
 * En que va una exportacion. RONDA-PLAN-MAESTRO.md sec. 13
 */
enum ExportStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return __('export-status.'.$this->value);
    }

    public function isDownloadable(): bool
    {
        return $this === self::Completed;
    }

    /**
     * Si todavia puede cambiar sola. La pantalla se refresca mientras haya
     * alguna asi, y deja de hacerlo cuando no queda ninguna.
     */
    public function isRunning(): bool
    {
        return $this === self::Queued || $this === self::Processing;
    }
}
