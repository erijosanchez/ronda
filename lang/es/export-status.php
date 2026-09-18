<?php

declare(strict_types=1);

// En que va una exportacion.
// Las claves son los valores de Ronda\Insights\Domain\ExportStatus.
return [
    'queued' => 'En cola',
    'processing' => 'Generando',
    'completed' => 'Lista',
    'failed' => 'Falló',
];
