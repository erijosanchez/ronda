# 0006. Actions y DTOs, controladores sin lógica

**Estado:** aceptado · **Fecha:** 2026-09-03

## Contexto
En reports-trimax la lógica de negocio quedó dentro de los controladores. Eso la hizo imposible de probar sin HTTP, imposible de reutilizar desde un job o un comando, e imposible de leer.

## Decisión
Toda escritura de negocio vive en una Action invocable de propósito único, que recibe un DTO tipado (`spatie/laravel-data`) y corre dentro de una transacción. El controlador valida, invoca y devuelve.

## Alternativas descartadas
- **Servicios gordos:** acaban siendo el mismo problema con otro nombre.
- **Lógica en el controlador:** ya se probó, produjo 1799 líneas.

## Consecuencias que se aceptan
Muchas más clases pequeñas. A cambio, cada regla de negocio se prueba sin HTTP y se reutiliza desde jobs, comandos y la API.
