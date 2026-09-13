# Dónde nos quedamos — 13 de septiembre de 2026

Estado del proyecto tras cerrar la **fase 0** y arrancar la fase 1.
El plan completo está en `../../RONDA-PLAN-MAESTRO.md`.
Léelo antes de retomar; termina con la lista concreta de lo que sigue.

---

## Resumen en una línea

Se entra a la aplicación y se aterriza en un panel de verdad. La autorización
está montada y probada. No hay ningún bloqueante abierto.

---

## Lo que funciona, verificado ejecutándolo

| Puerta | Resultado | Comando |
|---|---|---|
| Pint (formato) | **PASS**, 82 archivos | `vendor/bin/pint --test` |
| Larastan nivel 8 | **[OK] No errors**, sin baseline | `vendor/bin/phpstan analyse` |
| Rector | **[OK] Rector is done!** | `vendor/bin/rector process --dry-run` |
| Deptrac (módulos) | **0 violaciones** | `vendor/bin/deptrac analyse` |
| Pest | **58 pasan, 2 todos, 0 fallos** | `vendor/bin/pest` |

Todo junto: `docker compose exec app composer check` (sale 0).

**Recorrido completo comprobado:** se entra en `demo.localhost:8000/login`, se
aterriza en `/panel` con el nombre del usuario, el del cliente y sus roles
traducidos. En el dominio central ni `/login` ni `/panel` existen: 404.

**La suite tarda ~2 minutos.** No es un problema: la suite `Integration`
provisiona tenants reales (`CREATE DATABASE` + migrar + sembrar en cada
prueba), y sobre el bind mount de Windows cada arranque del framework se va a
unos 15 s. Para iterar, `--filter`.

---

## Lo que se hizo esta sesión

### Panel autenticado (punto 1 de la lista anterior)

El login redirigía a `/home`, que no existía: se entraba y se caía en un 404.
Ahora hay módulo `Insights` con un componente Livewire, su vista y su
`routes.php`, incluido desde el grupo autenticado de `routes/tenant.php`.

La ruta es `/panel`, no `/home`: las rutas van en español (`CLAUDE.md`).

El panel **no inventa indicadores**. Dice que aparecerán cuando haya sedes y
envíos. Un tablero con cifras falsas es peor que uno vacío.

### La CSP estricta y Livewire+Flux eran incompatibles

El evaluador de Alpine que Livewire empaqueta usa `new Function`, que
`unsafe-eval` bloquea. Sin él **ninguna directiva de Alpine se ejecuta**.
Livewire trae un build CSP-safe que lo evita, pero **Flux no funciona con él**:
sus componentes usan `$refs.input.click()`, `$dispatch(...)` y fragmentos JS
interpolados, y fallarían en silencio.

Decisión: `unsafe-eval` en `script-src`. `unsafe-inline` sigue fuera, que es la
parte que de verdad frena un XSS. Está en el **ADR 0011**, que corrige el ADR
0004: ese daba por hecho un Alpine «build CSP-safe» que no es viable con Flux
en el stack.

### Autorización (punto 2)

`OwnerGate` es el único `Gate::before` del proyecto. `UserPolicy` comprueba
**permisos**, no roles, para que el cliente reorganice sus roles sin tocar
código; protege al propietario y prohíbe borrarse a uno mismo.

### 🔴 Fuga de permisos entre tenants

`PermissionRegistrar` de spatie es un singleton que guarda los permisos en una
propiedad de instancia, y `loadPermissions()` hace cortocircuito si ya la
tiene. Nadie la vaciaba al cambiar de tenant, y bajo Octane el worker vive
entre peticiones.

**Reproducido:** un permiso creado solo en la base del tenant `alfa` aparecía
al consultar el registrar dentro de `beta`. El prefijo de caché por tenant no
ayuda: el problema es la colección en memoria, no el almacén.

Arreglado con `ForgetCachedPermissions` en `TenancyInitialized` (después de
`BootstrapTenancy`, que es quien conmuta la conexión) y en `TenancyEnded`, más
`register_octane_reset_listener` a `true`.

### Un test que pasaba sin comprobar nada

La primera versión de la prueba de esa fuga usaba
`expect($x)->not->toContain('valor', 'mensaje')`. **`toContain()` es variádico
en Pest**: el mensaje se tomó como un segundo valor a buscar y la aserción
quedó vacía — verde con el fallo presente. Conviene recordarlo: las
expectativas de Pest no aceptan un mensaje como último argumento salvo que su
firma lo diga (`toBeTrue`, `toBeEmpty`, `toBeNull` sí; `toContain` no).

---

## Puntos abiertos (ninguno bloquea)

### `Gate::before` salta por encima de `UserPolicy`

El propietario puede borrarse a sí mismo: `OwnerGate` devuelve `true` antes de
que la Policy llegue a ejecutarse. Ese invariante no es de autorización sino de
negocio («no se puede dejar al tenant sin propietario») y su sitio es la Action
que borre usuarios, cuando exista. Está anotado en el código.

### La regla «los DTOs son inmutables» choca con spatie/laravel-data

PHP no permite que una clase `readonly` extienda una que no lo es, y
`Spatie\LaravelData\Data` no lo es. Pest exige el modificador a nivel de clase,
así que **ningún DTO de spatie puede pasar la regla**. `CreateTenantData` se
resolvió como clase `readonly` propia. Hay que decidir cuál manda antes de que
un DTO tenga que atarse a un `Request`.

### La lista blanca de `toBeReadonly()` sigue creciendo

Cada namespace nuevo de `Presentation`, `Infrastructure` o `Database` hay que
añadirlo a mano. Esta sesión tocó otra vez. Con once módulos por delante,
conviene reformular la regla para que exija readonly solo en
`*\Application\Data`, que es su intención real.

### Cookie de sesión y colisión de identificadores

`SESSION_DOMAIN` está vacío, así que la cookie es host-only. Aun así, como los
identificadores de usuario empiezan en 1 en cada base, una cookie trasplantada
a mano al dominio de otro cliente resolvería el usuario 1 de esa base. En un
navegador no puede pasar; para defensa en profundidad, atar la sesión al
`tenant_id`.

---

## Lo que sigue, en orden

1. **Módulo `Directory`:** zonas, sedes, puestos y asignación usuario ↔ sede
   (plan §8.3). Es el siguiente cimiento: sin sedes no hay nada que programar
   ni que reportar. Traerá las primeras rutas autenticadas de verdad.
2. **Frontera por sede** (plan §10.3): el usuario ve solo las sedes asignadas,
   con Global Scope **más** verificación en la Policy. Dos capas, porque un
   scope se puede desactivar sin querer.
3. **Rellenar el `todo()` de aislamiento por rutas** en cuanto existan rutas
   autenticadas: recorrerlas todas con un usuario del otro tenant y exigir
   403/404.
4. **Gestión de usuarios en la interfaz.** `UserPolicy` ya existe pero no hay
   pantalla que la use, y la Action `DeleteUser` con el invariante del
   propietario está pendiente.
5. **Provisión asíncrona con pantalla de progreso** (plan §7.2). Hoy el job va
   a la cola pero nadie mira su estado.
6. **Sentry y Laravel Pulse** (plan §11.5). Solo hay logs.
7. **`lang/en` solo tiene `roles` y `permissions`**; le falta `validation`.

---

## Cómo retomar

```bash
cd c:/proyecto/ronda
docker compose up -d
docker compose ps                              # los ocho deben salir healthy
docker compose exec app php artisan migrate    # base central
docker compose exec app php artisan db:seed    # crea el tenant de demostración
npm run build                                  # assets (Flux va en el bundle)
docker compose exec app composer check         # las cinco puertas
```

- Portada (central): http://localhost:8000 — aquí **no** hay login, da 404.
- Aplicación (tenant): http://demo.localhost:8000/login
  - Usuario: `owner@demo.test` · Contraseña: `password-de-desarrollo`
- Mailpit: http://localhost:8025 · MinIO: http://localhost:9001
- PostgreSQL desde el host: `localhost:5433` · Redis: `localhost:6380`

Tras cambiar rutas, configuración o proveedores hay que reiniciar Octane, que
cachea la aplicación en memoria:

```bash
docker compose exec app php artisan optimize:clear
docker compose restart app
```

Para ver las bases de tenant existentes:

```bash
docker compose exec postgres psql -U ronda -d postgres -c "select datname from pg_database where datname like 'ronda_tnt%'"
```

Las reglas de código están en `../CLAUDE.md`. Las decisiones, en `adr/`.
