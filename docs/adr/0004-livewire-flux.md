# 0004. Livewire 3 + Flux + Alpine

**Estado:** aceptado · **Fecha:** 2026-09-03

## Contexto
El equipo es pequeño y de perfil PHP. Una SPA obliga a mantener una API interna, un segundo despliegue y un segundo lenguaje.

## Decisión
Livewire 3 con Flux UI para componentes y Alpine (build CSP-safe) para interactividad de cliente. Cero JavaScript en línea dentro de Blade.

## Alternativas descartadas
- **Inertia + Vue:** mejor para dashboards muy interactivos, más piezas móviles.
- **SPA + API separada:** el doble de trabajo sin cliente móvil que lo exija todavía.
- **Blade clásico:** repite el JS incrustado que dejó 12 724 líneas en 52 plantillas de reports-trimax.

## Consecuencias que se aceptan
Menos apto si aparece una pantalla extremadamente interactiva; se resolvería con un componente Alpine aislado. A cambio, la ausencia de JS en línea es lo que hace viable una CSP estricta desde el primer despliegue.
