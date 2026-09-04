# 0002. Base de datos por tenant

**Estado:** aceptado · **Fecha:** 2026-09-03

## Contexto
Ronda guarda datos financieros y de personal de empresas distintas. Una fuga entre clientes no es un bug: es el fin del negocio. reports-trimax usó una columna `sede` con global scope, y bastó un scope olvidado para que la frontera dejara de existir en varios controladores.

## Decisión
Una base PostgreSQL por tenant con `stancl/tenancy`. Una base central guarda solo tenants, dominios, planes, suscripciones y métricas de uso.

## Alternativas descartadas
- **`tenant_id` en base única:** más barato, pero un scope olvidado filtra datos. Ya pasó.
- **Schema por tenant:** aislamiento lógico fuerte con un solo pool de conexiones. Es el plan B.

## Consecuencias que se aceptan
Migrar N bases en cada despliegue, resuelto con orquestador en cola y migraciones expand/contract. Coste de conexiones de PostgreSQL, resuelto con PgBouncer. Si a los ~300 tenants el coste se vuelve problemático, pasar a schema-per-tenant es un cambio de configuración del mismo paquete, no una reescritura.
