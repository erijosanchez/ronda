# 0008. Obligaciones materializadas

**Estado:** aceptado · **Fecha:** 2026-09-03

## Contexto
reports-trimax solo puede medir lo que se envió. Saber que una sede jamás mandó el reporte del martes exige deducirlo de la ausencia de una fila, y esa deducción se rompe con cada cambio de calendario o de sede.

## Decisión
Un job diario materializa por adelantado cada entrega esperada como una fila en `obligations` (sede + plantilla + fecha límite), con estados `pending`, `fulfilled`, `missed` y `excused`.

## Alternativas descartadas
- **Deducir el incumplimiento por ausencia:** frágil, no auditable, e imposibilita el recordatorio anticipado.

## Consecuencias que se aceptan
Muchas más filas y un job que hay que vigilar. A cambio: el KPI de cumplimiento es una división trivial y auditable, el recordatorio previo al vencimiento es posible, las excepciones legítimas se registran en vez de ensuciar la métrica, y la pantalla del encargado pasa a ser su lista de pendientes en vez de un formulario en blanco.
