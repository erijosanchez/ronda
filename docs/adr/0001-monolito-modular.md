# 0001. Monolito modular

**Estado:** aceptado · **Fecha:** 2026-09-03

## Contexto
El producto arranca con uno o dos desarrolladores y sin tráfico. Los microservicios imponen un coste operativo (despliegue, observabilidad, consistencia entre servicios) que en esa etapa no se paga con nada.

## Decisión
Un solo despliegue con límites internos duros: módulos con capas Domain / Application / Infrastructure / Presentation, verificados por Deptrac en cada build.

## Alternativas descartadas
- **Microservicios:** coste operativo desproporcionado para el tamaño del equipo.
- **Monolito plano:** es lo que produjo controladores de 1799 líneas en reports-trimax.

## Consecuencias que se aceptan
Riesgo de acoplamiento entre módulos si nadie mira. Se mitiga con Deptrac en CI, no con disciplina. Cualquier módulo puede extraerse después si el volumen lo justifica.
