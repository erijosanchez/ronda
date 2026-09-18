<?php

declare(strict_types=1);

// Las cinco plantillas de arranque (RONDA-PLAN-MAESTRO.md sec. 3.5).
// Las claves son los valores de Ronda\Forms\Domain\CatalogTemplate.
return [

    'ARQUEO-CAJA' => [
        'name' => 'Arqueo de caja',
        'description' => 'Cuadre diario de la caja: saldo del sistema, efectivo contado, diferencia declarada, foto del arqueo y firma de quien entrega.',
        'cadence' => 'Diaria',
    ],

    'DEPOSITO' => [
        'name' => 'Depósito bancario',
        'description' => 'Depósito de la cobranza del día: monto, banco, número de operación y voucher del banco como evidencia.',
        'cadence' => 'Diaria',
    ],

    'APERTURA-CIERRE' => [
        'name' => 'Apertura y cierre de local',
        'description' => 'Una sola plantilla para las dos ventanas del día. Al cerrar pregunta además por la alarma y la caja. Con foto del local para contrastar la ubicación.',
        'cadence' => 'Diaria, dos ventanas',
    ],

    'INCIDENCIA' => [
        'name' => 'Reporte de incidencias',
        'description' => 'Se entrega cuando pasa algo: tipo, severidad, qué ocurrió, fotos y acciones tomadas. La severidad ordena la atención.',
        'cadence' => 'Por evento',
    ],

    'LIMPIEZA' => [
        'name' => 'Checklist de limpieza e imagen',
        'description' => 'Revisión por áreas (fachada, atención, servicios y almacén) con estado y foto de cada una, y un puntaje que alimenta el ranking de locales.',
        'cadence' => 'Semanal',
    ],

];
