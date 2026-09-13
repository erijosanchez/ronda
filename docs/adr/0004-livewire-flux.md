# 0004. Livewire 3 + Flux + Alpine

**Estado:** aceptado, revisado en parte por [0011](0011-csp-y-unsafe-eval.md) · **Fecha:** 2026-09-03

## Contexto
El equipo es pequeño y de perfil PHP. Una SPA obliga a mantener una API interna, un segundo despliegue y un segundo lenguaje.

## Decisión
Livewire 3 con Flux UI para componentes y Alpine para interactividad de cliente. Cero JavaScript en línea dentro de Blade.

> **Corregido el 2026-09-12.** Esta decisión decía «Alpine (build CSP-safe)».
> No es viable: Flux no funciona con ese evaluador. Ver [ADR 0011](0011-csp-y-unsafe-eval.md).

## Alternativas descartadas
- **Inertia + Vue:** mejor para dashboards muy interactivos, más piezas móviles.
- **SPA + API separada:** el doble de trabajo sin cliente móvil que lo exija todavía.
- **Blade clásico:** repite el JS incrustado que dejó 12 724 líneas en 52 plantillas de reports-trimax.

## Consecuencias que se aceptan
Menos apto si aparece una pantalla extremadamente interactiva; se resolvería con un componente Alpine aislado. A cambio, la ausencia de JS en línea permite mantener `unsafe-inline` fuera de la CSP, que es la parte que de verdad frena un XSS; `unsafe-eval` sí hace falta para el evaluador de Alpine (ADR 0011).
