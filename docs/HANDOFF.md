# Dónde nos quedamos — 3 de septiembre de 2026

Estado de la **fase 0** (fundaciones) del plan maestro (`../../RONDA-PLAN-MAESTRO.md`).
Léelo antes de retomar; termina con la lista concreta de lo que sigue.

---

## Resumen en una línea

La fase 0 está prácticamente completa y verificada dentro de Docker: las cinco
puertas de calidad pasan y el stack levanta. **Queda un bloqueante abierto:
`/login` devuelve 500 por Telescope.** Está diagnosticado y con solución
propuesta más abajo.

---

## Lo que funciona, verificado de verdad

Todo lo siguiente se comprobó ejecutándolo, no asumiéndolo.

| Puerta | Resultado | Comando |
|---|---|---|
| Pint (formato) | **PASS**, 42 archivos | `vendor/bin/pint --test` |
| Larastan nivel 8 | **[OK] No errors**, sin baseline | `vendor/bin/phpstan analyse` |
| Deptrac (módulos) | **0 violaciones** | `vendor/bin/deptrac analyse` |
| Rector | **[OK] Rector is done!** | `vendor/bin/rector process --dry-run` |
| Pest | **15 pasan, 3 todos, 0 fallos** | `vendor/bin/pest` |

**Stack local levantado y sano:** app (FrankenPHP + Octane), postgres 17,
redis 7.4, horizon, scheduler, reverb, mailpit, minio. Todos `healthy`.

**Migraciones aplicadas** sobre PostgreSQL (users, cache, jobs, tenants,
domains). Base de pruebas `ronda_testing` creada con sus extensiones.

**Endpoints comprobados:** `/` → 200, `/up` → 200, con las siete cabeceras de
seguridad y una CSP estricta con nonce por petición.

**Imagen de producción:** `ronda-app:local`, 462 MB, sin composer, sin node,
sin Chromium, corriendo como `www-data`.

---

## 🔴 Bloqueante abierto: `/login` da 500

**Síntoma.** `GET /login` devuelve 500 y tarda ~258 segundos. Bloquea un worker
de Octane mientras tanto.

**Causa raíz identificada.** Laravel Telescope se registra y escribe en la
tabla `telescope_entries`, que no existe porque nunca se publicaron sus
migraciones:

```
local.ERROR: SQLSTATE[42P01]: Undefined table: 7
ERROR: relation "telescope_entries" does not exist
```

**Por qué tarda 258 s en vez de fallar rápido.** El renderizador de excepciones
de Laravel escanea `vendor/` para pintar la página de error, y sobre el bind
mount de Docker Desktop en Windows eso es extremadamente lento. Acaba en
`Maximum execution time of 30 seconds exceeded`. Es un multiplicador del
problema, no la causa.

**Lo que ya se intentó (y no bastó).** Se añadió `laravel/telescope` a
`extra.laravel.dont-discover` en `composer.json` y se registra condicionalmente
solo en local desde `AppServiceProvider`. Se verificó que
`bootstrap/cache/packages.php` ya **no** menciona Telescope. Aun así el error
persiste tras reiniciar el contenedor, así que **falta identificar qué lo sigue
registrando** — sospechas por orden:

1. Los contenedores `horizon`, `scheduler` y `reverb` comparten el mismo bind
   mount y pudieron regenerar `bootstrap/cache/packages.php` con otro estado.
2. Algún caché de configuración o de eventos quedó escrito en `bootstrap/cache`.
3. El `composer dump-autoload` que se lanzó en el host se interrumpió por
   timeout y dejó el autoloader a medias.

**Camino de solución recomendado (30–45 min):**

```bash
docker compose down
rm -f bootstrap/cache/*.php
docker compose run --rm --no-deps app composer dump-autoload
docker compose up -d
docker compose exec app php artisan optimize:clear
docker compose logs app --tail=30
timeout 60 curl -si http://localhost:8000/login | head -20
```

Si sigue apareciendo, la decisión limpia es **quitar Telescope del proyecto**
(`composer remove --dev laravel/telescope`) y volver a evaluarlo en la fase 1
con sus migraciones publicadas y bien acotado a local. Es una herramienta de
depuración: no vale la pena que bloquee la fase 0. Laravel Pulse, que sí está
en el plan (§11.5), cubre buena parte de lo mismo en local y en producción.

**Truco para depurar más rápido:** poner `APP_DEBUG=false` en `.env` mientras se
investiga. Convierte el cuelgue de 258 s en un 500 inmediato y el error real
queda legible en `storage/logs/laravel.log`.

---

## Efecto colateral pendiente

El healthcheck del contenedor `app` marca **unhealthy** aunque la aplicación
responde 200 desde fuera y el mismo `curl` ejecutado a mano dentro del
contenedor devuelve 200 con exit 0. La causa más probable es que los workers de
Octane estaban saturados renderizando la excepción de `/login` durante 258 s, y
el healthcheck (timeout 5 s) no conseguía turno.

**Verificar de nuevo cuando `/login` esté arreglado.** Si persiste, subir
`--timeout` a 10 s y `--start-period` a 60 s en el `HEALTHCHECK` del
`Dockerfile`. Los healthchecks de `horizon`, `scheduler` y `reverb` sí pasan.

---

## Decisiones tomadas durante la ejecución

Van documentadas aquí porque afectan a cómo se trabaja en el repo:

1. **PHP local 8.2 vs proyecto 8.4.** El XAMPP de esta máquina tiene PHP 8.2 y
   no trae `pcntl`/`posix`. Se declaró la plataforma destino en
   `composer.json > config.platform`, así que Composer resuelve contra PHP 8.4
   y `php artisan` desde el host **falla a propósito**. Todo pasa por el
   contenedor. Está en `CLAUDE.md`.

2. **Chromium fuera de la imagen base.** Arrastraba 149 paquetes (GTK, LLVM).
   Es el riesgo 9 del plan: el PDF irá en un servicio aparte, en cola.

3. **Composer fuera de la imagen final.** El classmap optimizado se genera en
   la etapa `vendor`. Es el hallazgo I9 de la auditoría de reports-trimax.

4. **`routes/tenant.php` sin ruta `/`.** El stub de `stancl/tenancy` traía una
   que eclipsaba la portada central y hacía que `PreventAccessFromCentralDomains`
   devolviera 404. Documentado en el propio archivo.

5. **Fortify con features recortadas.** `registration`, `emailVerification` y
   `passkeys` quedan comentadas: son parte del onboarding self-service de la
   fase 2 y activarlas ahora deja rutas sin vista.

6. **Defecto corregido en `SecurityHeaders`.** El nonce se generaba *después*
   de `$next($request)`, o sea después de renderizar la vista: la CSP habría
   bloqueado los scripts de la propia aplicación. Ahora se genera antes y se
   propaga con `Vite::useCspNonce()`.

---

## Historial de commits

```
8f783de  fix: cierra las cinco puertas de calidad en verde
6c350c7  feat(platform): publica configuracion de tenancy, fortify y permisos
b4fc2f5  feat(platform): cabeceras de seguridad con CSP estricta, tenancy e i18n
631d7ac  chore: fundaciones del proyecto (fase 0)
```

Hay trabajo **sin commitear** en el árbol: vistas de autenticación,
`FortifyServiceProvider`, el arreglo del nonce y el intento de exclusión de
Telescope. Commitear cuando `/login` esté verde.

---

## Lo que sigue, en orden

### Para mañana (cierra la fase 0)

1. **Arreglar `/login`** siguiendo el camino de arriba. Es lo primero.
2. **Confirmar el healthcheck de `app`** una vez `/login` responda.
3. **Commitear** las vistas de auth, el `FortifyServiceProvider` y el arreglo
   del nonce.
4. **Seeder de arranque:** roles (`owner`, `admin`, `supervisor`, `encargado`),
   permisos base y un usuario de desarrollo, para poder entrar y probar el
   login de verdad.
5. **Test funcional de login:** credenciales correctas, incorrectas, rate limit
   a los 5 intentos, y reto de 2FA. Ahora mismo la autenticación no tiene
   ninguna prueba.
6. **Provisión de tenant de punta a punta:** `CreateTenant` + `ProvisionTenant`
   creando la base, migrando y sembrando. Es el corazón del ADR 0002 y todavía
   no existe.
7. **Llenar los tres `todo()`** de `tests/Feature/Tenancy/TenantIsolationTest.php`
   en cuanto haya dos tenants que enfrentar.

### Pendientes de la fase 0 que quedaron fuera

- Sentry y Laravel Pulse (plan §11.5). Solo están los logs.
- Layout autenticado con Flux (hoy solo existe `layouts/guest`).
- `lang/en` está vacío: solo hay `lang/es`.
- Instalar los plugins de Claude Code del §16.6 del plan (ninguno instalado
  todavía en esta máquina; Superpowers es el primero).

---

## Cómo retomar

```bash
cd c:/proyecto/ronda
docker compose up -d
docker compose ps                              # todo debe salir healthy
docker compose exec app php artisan migrate
docker compose exec app composer check         # pint + phpstan + deptrac + pest
```

- App: http://localhost:8000
- Mailpit: http://localhost:8025
- MinIO: http://localhost:9001
- PostgreSQL desde el host: `localhost:5433` · Redis: `localhost:6380`

Las reglas de código están en `../CLAUDE.md`. Las decisiones, en `adr/`.
