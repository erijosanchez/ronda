# 0003. PostgreSQL 17

**Estado:** aceptado · **Fecha:** 2026-09-03

## Contexto
El motor de formularios guarda respuestas de estructura variable. Necesita consultarlas, no solo almacenarlas. Además hay tablas de auditoría y eventos que crecen sin límite.

## Decisión
PostgreSQL 17 como único motor, en todos los entornos.

## Alternativas descartadas
- **MySQL 8.4:** más familiar para el equipo, pero su soporte de JSON no tiene equivalente a los índices GIN y el particionado es más rígido.

## Consecuencias que se aceptan
Curva de aprendizaje para el equipo. Se gana JSONB indexable (esencial para `submissions.data`), particionado nativo para `activity_log` y `sla_events`, CTEs, y row-level security como defensa en profundidad.
