# Dónde nos quedamos — 5 de septiembre de 2026

Estado de la **fase 0** (fundaciones) del plan maestro (`../../RONDA-PLAN-MAESTRO.md`).
Léelo antes de retomar; termina con la lista concreta de lo que sigue.

---

## Resumen en una línea

**La fase 0 está cerrada.** No queda ningún bloqueante: el stack levanta con los
ocho contenedores en verde, un tenant se provisiona de punta a punta con su
propia base, y se entra con usuario y contraseña en su subdominio. Las cinco
puertas de calidad pasan.

---

## Lo que funciona, verificado ejecutándolo

| Puerta | Resultado | Comando |
|---|---|---|
| Pint (formato) | **PASS**, 66 archivos | `vendor/bin/pint --test` |
| Larastan nivel 8 | **[OK] No errors**, sin baseline | `vendor/bin/phpstan analyse` |
| Rector | **[OK] Rector is done!** | `vendor/bin/rector process --dry-run` |
| Deptrac (módulos) | **0 violaciones** | `vendor/bin/deptrac analyse` |
| Pest | **38 pasan, 2 todos, 0 fallos** | `vendor/bin/pest` |

Todo junto: `docker compose exec app composer check` (sale 0).

**Stack:** app, postgres 17, redis 7.4, horizon, scheduler, reverb, mailpit,
minio. Los ocho `healthy`.

**Provisión de un tenant, comprobada de verdad:** `CreateTenant` crea la fila y
el dominio, `ProvisionTenantJob` crea `ronda_tnt_<ulid>`, migra el esquema,
siembra 4 roles y 16 permisos, crea al propietario con rol `owner` y emite
`TenantProvisioned`.

**Login:** `POST /login` en `demo.localhost:8000` devuelve 302 con la sesión
abierta. En el dominio central `/login` devuelve **404**, que es lo correcto.

---

## Lo que se arregló esta sesión

### El 500 de `/login` no era Telescope

El handoff anterior lo atribuía a Laravel Telescope. No lo era. La vista abre
con el componente `layouts.guest`, que Blade resuelve en
`resources/views/components/layouts/`, y el layout estaba en
`resources/views/layouts/`. El componente no existía.

Los errores de `telescope_entries` que llevaron al diagnóstico equivocado los
emitían los contenedores `horizon`, `reverb` y `scheduler`: procesos de larga
vida que seguían con el paquete cargado en memoria y escribían en el log cada
cinco segundos. Aparecían en el log de la misma petición y despistaron.

Los 258 segundos eran el renderizador de excepciones recorriendo `vendor/` sobre
el bind mount de Windows. Un multiplicador, no la causa.

Telescope se quitó igualmente (`composer remove`): sus migraciones nunca se
publicaron. Pulse cubre el hueco (plan §11.5).

### El healthcheck del `app` nunca pudo pasar

Era `php artisan octane:status`, que arranca el framework entero y **tarda 17,3 s
sobre el bind mount**, contra un timeout de 5 s. No era saturación de workers.
Ahora comprueba `/up` por HTTP, que además mide lo que importa: servir
peticiones, no arrancar un proceso.

### La suite de tests corría contra la base de desarrollo

El hallazgo más serio. `docker-compose` inyecta `.env` con `env_file`, así que
`APP_ENV`, `DB_DATABASE` y `QUEUE_CONNECTION` ya existían como variables del
proceso. PHPUnit no pisa variables ya definidas sin `force="true"` — y `force`
solo toca `getenv()` y la superglobal `$_ENV`, mientras que en CLI las variables
de Docker también están en `$_SERVER`, que es lo **primero** que consulta el
lector de Dotenv de Laravel.

Resultado: toda la suite usaba `ronda_central` y la cola de redis. No es
teórico: una prueba con `DatabaseMigrations` ejecutó `migrate:fresh` sobre la
base de desarrollo y la dejó vacía, con nueve bases de tenant huérfanas.

Arreglado con entradas `env` **y** `server` con `force="true"` en `phpunit.xml`,
y `tests/Feature/Security/TestEnvironmentTest.php` para que la CI lo detecte si
alguien lo revierte.

### Rector iba a romper el login otra vez

`StringToClassConstantRector` convertía la vista `auth.login` en la constante
`Illuminate\Auth\Events\Login::class`: su tabla mapea el nombre del evento
legacy de Laravel 5 y colisiona con el nombre de nuestra vista Blade.
Desactivada con justificación en `rector.php`.

### Otros

- **Composer no estaba en el contenedor**, así que el `composer check` que manda
  `CLAUDE.md` nunca pudo ejecutarse. Nueva etapa `dev` del Dockerfile
  (`runtime` + Composer); producción sigue construyendo `runtime`.
- `composer check` corría cuatro puertas y la CI cinco. Ahora incluye Rector.
- `phpunit.xml` no miraba `src/*/Tests`, la carpeta que manda `CLAUDE.md`, y la
  cobertura solo medía `app/`. Ambas corregidas.

---

## Decisiones tomadas esta sesión

1. **Se invirtió el orden del handoff anterior.** El seeder de roles y el test
   de login dependían de la provisión de tenant, no al revés: usuarios, roles y
   permisos viven en la base del tenant (plan §8.3), y esa base no existía.

2. **La central se queda sin tabla `users`.** Es el invariante del ADR 0002 y
   hay un test que lo verifica. `platform_users` (personal de Ronda) se pospone
   a su propia fase; hoy **nadie inicia sesión en el dominio central**.

3. **La provisión no cuelga de `TenantCreated`.** Eloquent emite ese evento
   dentro de la transacción que abre `CreateTenant`, y PostgreSQL prohíbe
   `CREATE DATABASE` dentro de un bloque de transacción. El `JobPipeline` de
   stancl queda vacío y `CreateTenant` despacha el job **después del commit**.

4. **Las rutas de Fortify se cargan en `routes/tenant.php`.** Fortify las
   registra en el grupo `web` del dominio central, sin tenancy. Se desactiva su
   registro con `Fortify::ignoreRoutes()`.

5. **El rol `encargado` se identifica como `site_manager`.** La convención es
   código en inglés e interfaz en español; el nombre visible sale de `__()`.

6. **`CreateTenantData` no extiende `Spatie\LaravelData\Data`.** Ver el punto
   abierto de abajo.

7. **Suite `Integration` sin `RefreshDatabase`.** `CREATE DATABASE` no cabe
   dentro de la transacción con la que envuelve cada prueba.

---

## Puntos abiertos (ninguno bloquea)

### La regla «los DTOs son inmutables» choca con spatie/laravel-data

PHP no permite que una clase `readonly` extienda una que no lo es, y
`Spatie\LaravelData\Data` no es readonly. El test de arquitectura exige el
modificador a nivel de clase (Pest comprueba `ReflectionClass::isReadOnly()`),
así que **ningún DTO de spatie puede pasar la regla**.

`CreateTenantData` se resolvió como clase `readonly` propia porque no necesitaba
nada del paquete. **Hay que decidir cuál de las dos cosas manda antes de que un
DTO tenga que atarse a un `Request`**, que es donde spatie aporta de verdad. Las
opciones: DTOs planos y quitar el paquete de esa función, o relajar la regla a
«todas las propiedades readonly» con una expectativa propia de Pest.

### La lista blanca de `toBeReadonly()` crece con cada namespace

`tests/Arch/ArchitectureTest.php` exige readonly a **todo** e ignora una lista
que hay que ampliar cada vez que aparece una carpeta nueva. Es «denegar por
defecto», igual que `RouteProtectionTest`, pero conviene revisarlo cuando entren
más módulos.

### Cookie de sesión y colisión de identificadores

`SESSION_DOMAIN` está vacío, así que la cookie es host-only y no viaja entre
subdominios. Hay un test que lo fija. Aun así, como los identificadores de
usuario empiezan en 1 en cada base, una cookie trasplantada a mano al dominio de
otro cliente resolvería el usuario 1 de esa base. En un navegador no puede
pasar; si se quiere defensa en profundidad, atar la sesión al `tenant_id`.

---

## Lo que sigue, en orden

1. **Ruta y layout autenticados.** El login redirige a `/home`
   (`config/fortify.home`) y **esa ruta no existe**. Es lo primero: hoy se entra
   y se aterriza en un 404. Hace falta el layout autenticado con Flux; solo
   existe el componente `layouts.guest`.
2. **Policies y `Gate::before` para el rol `owner`** (plan §10.3). Los permisos
   ya están sembrados pero todavía no los usa nadie.
3. **Módulo `Directory`:** zonas, sedes y asignación usuario ↔ sede (plan §8.3).
   Es el primero que registrará rutas autenticadas de tenant.
4. **Rellenar el `todo()` de aislamiento por rutas** en cuanto exista la primera
   ruta autenticada: recorrerlas todas con un usuario del otro tenant.
5. **Provisión asíncrona con pantalla de progreso** (plan §7.2). Hoy el job va a
   la cola pero nadie mira su estado; el objetivo del plan es menos de 30 s con
   progreso visible.
6. **Sentry y Laravel Pulse** (plan §11.5). Solo hay logs.
7. **`lang/en` está vacío**; solo existe `lang/es`. Faltan además las claves
   `roles.*` y `permissions.*` que usan `RoleName::label()` y
   `PermissionName::label()`.

---

## Cómo retomar

```bash
cd c:/proyecto/ronda
docker compose up -d
docker compose ps                              # los ocho deben salir healthy
docker compose exec app php artisan migrate    # base central
docker compose exec app php artisan db:seed    # crea el tenant de demostración
docker compose exec app composer check         # las cinco puertas
```

- Portada (central): http://localhost:8000 — aquí **no** hay login, da 404.
- Aplicación (tenant): http://demo.localhost:8000/login
  - Usuario: `owner@demo.test` · Contraseña: `password-de-desarrollo`
- Mailpit: http://localhost:8025 · MinIO: http://localhost:9001
- PostgreSQL desde el host: `localhost:5433` · Redis: `localhost:6380`

Para ver las bases de tenant existentes:

```bash
docker compose exec postgres psql -U ronda -d postgres -c "select datname from pg_database where datname like 'ronda_tnt%'"
```

Las reglas de código están en `../CLAUDE.md`. Las decisiones, en `adr/`.
