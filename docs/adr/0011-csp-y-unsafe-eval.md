# 0011. CSP con `unsafe-eval` para el evaluador de Alpine

**Estado:** aceptado · **Fecha:** 2026-09-12 · **Revisa:** [0004](0004-livewire-flux.md)

## Contexto

El ADR 0004 decidió Livewire 3 + Flux + **Alpine en su build CSP-safe**, y el
plan (§10.5) se comprometió a una CSP estricta desde el primer despliegue:
`script-src 'self' 'nonce-<X>'`, sin `unsafe-inline` ni `unsafe-eval`.

Al montar el primer layout autenticado se comprobó que las dos cosas no pueden
sostenerse a la vez:

- El evaluador de Alpine que Livewire empaqueta usa
  `new Function(["scope"], "with (scope) { ... }")`. Verificado en
  `vendor/livewire/livewire/dist/livewire.min.js`, no supuesto. `unsafe-eval`
  lo bloquea, así que **ninguna directiva de Alpine se evalúa** sin él.
- Livewire sí trae un build CSP-safe (`livewire.csp.min.js`, sin `new Function`,
  activable con `csp_safe` en `config/livewire.php`).
- Pero **Flux no es compatible con ese build**. Sus componentes usan
  expresiones que el evaluador CSP-safe no admite: `$refs.input.click()`,
  `$dispatch('flux-sidebar-toggle')` y fragmentos JS interpolados desde PHP.
  Con `csp_safe` activo, esos componentes fallan **en silencio**: el servidor
  responde 200 y el botón no hace nada.

La premisa del ADR 0004 («Alpine build CSP-safe») era por tanto incorrecta:
no es una opción disponible mientras Flux esté en el stack.

## Decisión

Añadir `'unsafe-eval'` a `script-src`. La CSP queda:

```
script-src 'self' 'nonce-<X>' 'unsafe-eval'
```

**`unsafe-inline` sigue fuera, y eso es lo que importa.** La defensa real
contra XSS es el nonce: sin él, un atacante no puede hacer que el navegador
ejecute una etiqueta `<script>` inyectada, ni un manejador `onclick=`, ni un
`javascript:`. `unsafe-eval` solo abre la puerta si además existe un gadget ya
presente en la aplicación que pase entrada del atacante a `eval`.

`config/livewire.php` no se publica y `csp_safe` queda en su valor por defecto
(`false`), que es el build normal.

## Alternativas descartadas

- **`csp_safe` a `true` y renunciar a parte de Flux.** Mantiene la CSP intacta,
  pero rompe componentes sin ningún aviso y convierte cada componente nuevo de
  Flux en una mina. Se acabaría reescribiendo a mano lo que Flux ya resuelve.
- **Quitar Flux y usar Tailwind a pelo.** CSP intacta y sin sorpresas, pero
  contradice el ADR 0004 y obliga a construir menú, barra lateral y desplegables
  desde cero.
- **Esperar a que Flux sea compatible con el evaluador CSP-safe.** No hay
  compromiso público de que vaya a serlo, y bloquea la fase 1.

## Consecuencias que se aceptan

- Si algún día aparece un gadget que llegue a `eval` con datos de usuario, la
  CSP no lo frenará. Se mitiga con lo de siempre: nada de `{!! !!}` en Blade
  (ya prohibido), y ninguna entrada de usuario cerca de `eval`.
- `@alpinejs/csp` se elimina de `package.json`: estaba declarado pero nunca se
  importaba, porque Livewire empaqueta su propio Alpine.
- Queda un test (`tests/Feature/Security/ContentSecurityPolicyTest.php`) que
  fija la política: falla si alguien añade `unsafe-inline`, y documenta por qué
  `unsafe-eval` está permitido para que no se borre por error.
- Si Flux llegara a soportar el evaluador CSP-safe, este ADR se revisa y
  `unsafe-eval` se retira.
