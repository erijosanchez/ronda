## Que cambia

<!-- Una frase. Que hace distinto el sistema despues de este PR. -->

## Por que

<!-- El problema, no la solucion. Enlaza el issue o el ADR si aplica. -->

## Checklist — definicion de terminado (RONDA-PLAN-MAESTRO.md sec. 12.5)

- [ ] Pasa Pint, Larastan 8, Rector y Deptrac
- [ ] Tiene pruebas unitarias y funcionales con la cobertura exigida
- [ ] Tiene Policy y esta cubierto por la suite de aislamiento de tenant
- [ ] Los textos pasan por `__()` y existen en `es` y `en`
- [ ] Los listados paginan y no tienen N+1
- [ ] Usable con teclado, contraste y etiquetas WCAG 2.1 AA
- [ ] Funciona en movil (se disena movil primero)
- [ ] Registro de auditoria si toca dinero, permisos o evidencia
- [ ] Documentado en el manual si es visible para el cliente
- [ ] Errores instrumentados en Sentry con contexto util

## Riesgo

<!-- Que se rompe si esto sale mal, y como se revierte. -->
