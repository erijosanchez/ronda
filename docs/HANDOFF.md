# Dónde nos quedamos — 16 de septiembre de 2026

Estado del proyecto: **fase 0 cerrada; fase 1 con los tres primeros eslabones
del motor en pie**.
El plan completo está en `../../RONDA-PLAN-MAESTRO.md`, documento interno que
no forma parte de este repositorio (el repositorio es público).

---

## Resumen en una línea

Se diseñan plantillas, se programan, y el motor materializa las obligaciones y
marca las incumplidas cada hora. Lo que falta es que una sede **entregue**: el
módulo `Submissions`.

```
PLANTILLA ✅ → PROGRAMACIÓN ✅ → OBLIGACIÓN ✅ → ENVÍO → FLUJO → KPI
```

---

## Lo que funciona, verificado ejecutándolo

Todo junto: `docker compose exec app composer check` (pint + phpstan + rector +
deptrac + pest). La suite tarda **~5 minutos**: la suite `Integration`
provisiona tenants reales en cada prueba. Para iterar, `--filter`.

| Módulo | Qué hace | Pantallas |
|---|---|---|
| `Platform` | Provisión de tenant con base propia, aislamiento de sesión | — |
| `Identity` | Usuarios, roles, permisos, 2FA, invariantes del propietario | `/usuarios` |
| `Directory` | Zonas, sedes, cargos, asignación persona ↔ sede, frontera por sede | `/sedes`, `/usuarios/{id}/sedes` |
| `Forms` | Plantillas versionadas, diseñador visual, publicación | `/plantillas` |
| `Scheduling` | Programaciones RRULE, feriados, materialización de obligaciones | — (sin pantalla aún) |
| `Insights` | Panel de aterrizaje | `/panel` |

---

## Lo que se hizo en las últimas sesiones

### `Forms` — plantillas versionadas (ADR 0012)

Una versión publicada **no se modifica nunca**; hay un test que publica dos
veces y comprueba que la primera sigue intacta. PostgreSQL no valida el
contenido de un JSONB, así que `Field` y `FormSchema` son la única barrera:
impiden claves repetidas, marcar obligatoria una sección, marcar reportable una
firma o condiciones que apuntan a campos inexistentes.

**No hay borradores.** El plan (§9.2) dice «guardar publica una versión nueva».
Llegué a introducirlos por mi cuenta y los retiré: `published_at` es `NOT NULL`.

El diseñador creaba la plantilla **antes** de comprobar el permiso de publicar y
dejaba huérfanas; ahora autoriza antes de tocar nada.

### `Scheduling` — el motor de obligaciones (ADR 0008)

- Reglas **RRULE** con `rlanvin/php-rrule` (RFC 5545 tiene demasiadas esquinas
  para reescribirla). Se validan al guardar, no de madrugada.
- `ObligationPlanner` es **puro**: toda la lógica de calendario se prueba sin
  base de datos (18 pruebas unitarias).
- La ventana horaria se interpreta **en la zona de cada sede**, no en una zona
  de la programación. Desviación consciente respecto a la tabla del plan,
  documentada en la migración.
- La materialización es **idempotente** (restricción única por programación +
  sede + día local, e `insertOrIgnore`). Corre cada hora sobre un horizonte de
  14 días sin duplicar.
- Las obligaciones son una **foto**: si la programación cambia, las ya creadas
  no se mueven.
- Estados con máquina declarativa: `missed → fulfilled` **no existe**, porque
  reescribiría el cumplimiento de una semana ya reportada. `missed → excused` sí.
- Comando `obligations:materialize`, un job por tenant, programado cada hora.

---

## 🔴 Antes de producción: verificar los feriados

`PeruvianHolidays` es una **semilla calculada, no la fuente oficial**. El
Ejecutivo añade días no laborables por decreto con poca antelación, y algunos
feriados son recientes. **Hay que contrastar la lista con El Peruano**,
empezando por el 7 de junio, 23 de julio, 6 de agosto y 9 de diciembre. La
columna `holidays.source` existe para sustituir la semilla por una
sincronización oficial sin tocar lo que haya cargado el cliente a mano.

Solo hay feriados **nacionales**; los regionales necesitan modelar la región de
cada sede.

---

## Puntos abiertos (ninguno bloquea)

- **Zonas y cargos no tienen pantalla.** El formulario de sede ofrece zonas pero
  no se pueden crear desde la interfaz.
- **Programaciones sin pantalla.** El dominio y el motor están completos; falta
  la interfaz para crearlas. Hoy solo por código.
- **`last_login_at` nadie la escribe.** Falta un listener del evento `Login`.
- **DTOs vs spatie/laravel-data.** PHP no deja que una clase `readonly` extienda
  `Data`, y la regla de arquitectura exige DTOs readonly. Decidir antes de atar
  un DTO a un `Request`.
- **Ventanas que cruzan la medianoche** no se admiten. Si un cliente lo necesita
  (turno de noche), hay que modelarlo explícitamente.

---

## Lo que queda del plan, en orden

1. **`Submissions`** — la entrega: responder una plantilla contra una
   obligación, guardar `data` JSONB, replicar lo reportable en
   `submission_values` dentro de la misma transacción (ADR 0012), y pasar la
   obligación a `fulfilled`. Es el siguiente eslabón y el que hace útil todo lo
   anterior.
2. **Pantalla de programaciones** y **lista de pendientes de hoy** del
   encargado (§9.3: «cambia la adopción»).
3. **`Evidence`** — fotos con SHA-256, geoetiqueta y URL firmada (ADR 0009).
4. **`Workflow`** — revisión y aprobación con máquina de estados (§9.4).
5. **`Notifications`** — recordatorios y escalamiento.
6. **`Insights`** — KPI de cumplimiento: `fulfilled / (fulfilled + missed)`.
7. **`Api`**, Sentry/Pulse, provisión asíncrona con progreso.

---

## Cómo retomar

```bash
cd c:/proyecto/ronda
docker compose up -d
docker compose ps                                     # los ocho healthy
docker compose exec app php artisan migrate           # base central
docker compose exec app php artisan db:seed           # tenant de demostración
docker compose exec app php artisan tenants:migrate   # migraciones de módulo
docker compose exec app php artisan tenants:seed      # roles, cargos, feriados
npm run build
docker compose exec app composer check
```

- Aplicación: http://demo.localhost:8000/login — `owner@demo.test` · `password-de-desarrollo`
- Portada central: http://localhost:8000 (sin login, a propósito)
- Materializar a mano: `docker compose exec app php artisan obligations:materialize --sync`

Tras cambiar rutas, configuración o proveedores, reiniciar Octane:
`docker compose exec app php artisan optimize:clear && docker compose restart app`

---

## Costumbre que vale la pena mantener

Aparecieron **dos pruebas de seguridad que pasaban sin comprobar nada**
(`toContain()` variádico; un recorrido de rutas que solo barría Horizon). Se
encontraron imprimiendo lo que la prueba recorría de verdad. Una prueba que pasa
a la primera conviene sondearla, o romper a propósito lo que debería detectar.

Las reglas de código están en [`../CLAUDE.md`](../CLAUDE.md); las decisiones,
en [`adr/`](adr/).
