# Dónde nos quedamos — 13 de septiembre de 2026

Estado del proyecto: **fase 0 cerrada, fase 1 en marcha**.
El plan completo está en `../../RONDA-PLAN-MAESTRO.md`, documento interno que
no forma parte de este repositorio (el repositorio es público).
Léelo antes de retomar; termina con la lista concreta de lo que sigue.

---

## Resumen en una línea

Se entra, se aterriza en un panel, se listan las sedes del cliente, y un
usuario de un tenant ya no puede alcanzar los datos de otro por ninguna ruta.
No hay ningún bloqueante abierto.

---

## Lo que funciona, verificado ejecutándolo

| Puerta | Resultado | Comando |
|---|---|---|
| Pint (formato) | **PASS**, 96 archivos | `vendor/bin/pint --test` |
| Larastan nivel 8 | **[OK] No errors**, sin baseline | `vendor/bin/phpstan analyse` |
| Rector | **[OK] Rector is done!** | `vendor/bin/rector process --dry-run` |
| Deptrac (módulos) | **0 violaciones** | `vendor/bin/deptrac analyse` |
| Pest | **68 pasan, 1 todo, 0 fallos** | `vendor/bin/pest` |

Todo junto: `docker compose exec app composer check` (sale 0).

**Recorrido comprobado a mano:** login en `demo.localhost:8000`, `/panel` con
nombre, cliente y roles traducidos, y `/sedes` con las cuatro sedes de prueba,
su zona, su horario y la cerrada marcada como tal. En el dominio central no
existe ninguna de las dos: 404.

**La suite tarda ~2 minutos** porque la suite `Integration` provisiona tenants
reales (`CREATE DATABASE` + migrar + sembrar en cada prueba). Para iterar,
`--filter`.

---

## Lo que se hizo esta sesión

### Módulo `Directory` (plan §8.3)

Zonas con jerarquía propia, sedes, cargos y la asignación usuario ↔ sede. Cada
sede lleva su zona horaria y su ventana de operación en hora local: una sede de
Iquitos y otra de Lima no abren a la misma hora UTC.

Pantalla `/sedes`, paginada, con búsqueda y filtro de vigentes.

La tabla pivote se llama `user_site`, como dice el plan, y no `site_user`, que
es lo que Laravel deduciría por orden alfabético. La relación lo declara a mano.

**Las migraciones de módulo no se aplicaban.** `CLAUDE.md` las sitúa en
`src/<Modulo>/Database/Migrations`, pero tenancy solo miraba
`database/migrations/tenant`. Ahora `--path` incluye `src/*/Database/Migrations`.

### Frontera por sede (plan §10.3), en dos capas

`AssignedSitesScope` filtra la consulta por `user_site`; `SitePolicy` vuelve a
comprobar sobre el registro. No es redundancia: hay un test que desactiva el
scope con `withoutGlobalScopes()` y exige que la Policy siga negando.

El scope **no decide autorización**: pregunta por la habilidad `viewAll` y
responde la Policy. Un `hasRole()` dentro de un scope sería justo lo que
prohíbe la regla 4.

### 🔴 Una sesión de un tenant servía en el dominio de otro

Al rellenar el `todo()` de aislamiento por rutas apareció una fuga real.

La sesión solo guarda el identificador numérico del usuario, y los
identificadores empiezan en 1 en cada base. Presentando en `beta` una sesión
abierta en `alfa`, el guard resolvía el usuario 1 de beta y entraba como él.
Medido: `GET beta/sedes` devolvía 200 con los datos de beta, y
`GET beta/user/two-factor-recovery-codes` devolvía los códigos de recuperación
del usuario de beta.

En un navegador no ocurre — `SESSION_DOMAIN` está vacío y la cookie es
host-only — pero bastaba con trasladar la cookie a mano.

Arreglado con `EnsureSessionBelongsToTenant`, que anota en la sesión el tenant
donde se abrió y la destruye si se presenta en otro. **Corta la petición en el
acto** en lugar de delegar en el middleware `auth`: Laravel ordena por
prioridad y `Authenticate` corre antes, así que la primera petición se servía
entera y solo las siguientes quedaban protegidas.

### Segundo test que pasaba sin comprobar nada

El recorrido de rutas filtraba por middleware que contuviera `Authenticate`, y
eso solo casa con la clase de Horizon: **`/panel` y `/sedes` nunca se pedían**.
Verde sin haber tocado una pantalla de la aplicación. `gatherMiddleware()` no
expande los grupos, así que el alias llega como `'auth'` a secas.

Van dos en dos sesiones (antes fue `toContain()` variádico). **Conviene sondear
toda prueba de seguridad que pase a la primera**: imprimir lo que realmente
recorre o comparar, antes de darla por buena. Ahora hay un test ancla que exige
que el recorrido incluya `panel` y `sedes`.

### La regla de arquitectura que había que reformular

La lista blanca de `toBeReadonly()` se escribía a mano y hubo que ampliarla
tres sesiones seguidas. Ahora se deriva de los once módulos y las capas, que
son fijas. Sigue siendo denegar por defecto; comprobado rompiendo
`CreateTenantData` a propósito y viendo fallar la regla.

---

## Puntos abiertos (ninguno bloquea)

### `Gate::before` salta por encima de `UserPolicy`

El propietario puede borrarse a sí mismo: `OwnerGate` devuelve `true` antes de
que la Policy se ejecute. Ese invariante es de negocio («no dejar al tenant sin
propietario») y su sitio es la Action que borre usuarios, cuando exista.

### La regla «los DTOs son inmutables» choca con spatie/laravel-data

PHP no permite que una clase `readonly` extienda una que no lo es, y
`Spatie\LaravelData\Data` no lo es. Pest exige el modificador a nivel de clase,
así que ningún DTO de spatie puede pasar la regla. `CreateTenantData` se
resolvió como clase `readonly` propia. Hay que decidir cuál manda antes de que
un DTO tenga que atarse a un `Request`.

### `positions` y `zones` no tienen pantalla

Las tablas y los modelos existen y el seeder siembra cinco cargos, pero no hay
interfaz para gestionarlos ni Policy propia. Solo `Site` la tiene.

### La tabla `users` del tenant está incompleta

El plan §8.3 pide también teléfono (con cast `encrypted`), estado y último
acceso. Hoy solo existe `last_login_at`, y nadie lo escribe.

---

## Lo que sigue, en orden

1. **Alta y edición de sedes.** El listado existe y el botón «Nueva sede» está
   puesto pero deshabilitado. Faltan las Actions `CreateSite` / `UpdateSite`
   con sus DTOs y el formulario.
2. **Gestión de usuarios en la interfaz.** `UserPolicy` existe pero no hay
   pantalla que la use, y falta la Action `DeleteUser` con el invariante del
   propietario.
3. **Asignación usuario ↔ sede desde la interfaz.** La tabla y las relaciones
   están; falta la pantalla que las use, que es lo que da sentido a la frontera
   por sede.
4. **Provisión asíncrona con pantalla de progreso** (plan §7.2). Hoy el job va
   a la cola pero nadie mira su estado; el objetivo del plan es menos de 30 s
   con progreso visible.
5. **Sentry y Laravel Pulse** (plan §11.5). Solo hay logs.
6. **`lang/en` solo tiene `roles` y `permissions`**; le falta `validation`.

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

Tras cambiar migraciones de tenant, aplicarlas al parque:

```bash
docker compose exec app php artisan tenants:migrate
docker compose exec app php artisan tenants:seed
```

Las reglas de código están en [`../CLAUDE.md`](../CLAUDE.md). Las decisiones,
en [`adr/`](adr/).
