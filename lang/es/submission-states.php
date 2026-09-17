<?php

declare(strict_types=1);

// Nombres visibles de los estados de un envio.
// Las claves son los valores de Ronda\Submissions\Domain\States.
return [
    'draft' => 'Borrador',
    'submitted' => 'Por revisar',
    'under_review' => 'En revisión',
    'approved' => 'Aprobado',
    'rejected' => 'Rechazado',
];
