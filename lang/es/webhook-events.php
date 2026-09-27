<?php

declare(strict_types=1);

/**
 * Nombres de los eventos de webhook. RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * La clave es el nombre publico del evento y no se traduce nunca: viaja en el
 * cuerpo del aviso y es contrato. Esto es solo lo que se lee en pantalla.
 */
return [
    'submission.submitted' => 'Alguien entrega un reporte',
    'submission.approved' => 'Se aprueba un reporte',
    'submission.rejected' => 'Se devuelve un reporte',
    'obligation.missed' => 'Vence un reporte sin entregar',
];
