<?php

declare(strict_types=1);

/*
 * Que puede hacer un token de la API (sec. 13.1). Se describen por lo que
 * permiten, no por su nombre tecnico: quien crea el token esta decidiendo que
 * delega.
 */

return [
    'sites:read' => 'Leer las sedes',
    'sites:write' => 'Crear y modificar sedes',
    'templates:read' => 'Leer las plantillas y sus campos',
    'obligations:read' => 'Leer lo que cada sede debe entregar',
    'submissions:read' => 'Leer los reportes entregados',
    'submissions:write' => 'Entregar reportes',
];
