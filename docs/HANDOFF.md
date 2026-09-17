# Dónde nos quedamos — 16 de septiembre de 2026

Estado del proyecto: **fase 0 cerrada; fase 1 con los cuatro primeros
eslabones del motor en pie y operables desde la interfaz, con evidencia
probatoria**.
El plan completo está en `../../RONDA-PLAN-MAESTRO.md`, documento interno que
no forma parte de este repositorio (el repositorio es público).

---

## Resumen en una línea

Se diseñan plantillas, se programan **desde la pantalla**, el motor materializa
las obligaciones, y el encargado ve su lista de pendientes de hoy y entrega el
reporte **con fotos, archivos y firma**, que cumple la obligación. Lo que falta es **revisar** lo entregado y **medir**.

```
PLANTILLA ✅ → PROGRAMACIÓN ✅ → OBLIGACIÓN ✅ → ENVÍO ✅ → FLUJO → KPI
```

---

## Lo que funciona, verificado ejecutándolo

Todo junto: `docker compose exec app composer check` (pint + phpstan + rector +
deptrac + pest). La suite tarda **~7 minutos** (por eso `composer.json` fija `process-timeout: 0`; con el límite de 300 s de Composer, `check` se cortaba): la suite `Integration`
provisiona tenants reales en cada prueba. Para iterar, `--filter`.

| Módulo | Qué hace | Pantallas |
|---|---|---|
| `Platform` | Provisión de tenant con base propia, aislamiento de sesión | — |
| `Identity` | Usuarios, roles, permisos, 2FA, invariantes del propietario | `/usuarios` |
| `Directory` | Zonas, sedes, cargos, asignación persona ↔ sede, frontera por sede | `/sedes`, `/usuarios/{id}/sedes` |
| `Forms` | Plantillas versionadas, diseñador visual, publicación | `/plantillas` |
| `Scheduling` | Programaciones RRULE, feriados, materialización y replanificación de obligaciones | `/programaciones` |
| `Submissions` | Entrega de reportes, validación contra la versión, réplica reportable, ficha del envío | `/pendientes`, `/envios/{id}` |
| `Evidence` | Fotos, archivos y firma en bucket privado; SHA-256, EXIF, distancia a la sede, URL firmada | `/evidencia/{id}` (firmada) |
| `Insights` | Panel de aterrizaje | `/panel` |

---

## Lo que se hizo en las últimas sesiones

### `Evidence` — evidencia con valor probatorio (ADR 0009, §9.5)

- **Bucket privado.** Disco `evidence` (S3: MinIO en local, R2 en producción),
  `throw` activo. El servicio `minio-init` de docker compose crea el bucket y lo
  deja sin acceso anónimo (la imagen `minio/mc` ya no se publica; se usa el `mc`
  que trae `minio/minio`). Rutas `tenants/<id>/<año>/<mes>/<ulid>.<ext>`: el
  prefijo del tenant se pone a mano para poder purgar por cliente.
- **Validación por contenido** (`finfo`), lista blanca por clase
  (`EvidenceKind`): foto = JPEG/PNG/WEBP; archivo = eso + PDF, XLSX, DOCX;
  firma = solo PNG. **Nunca SVG.** Máximo 10 MB (`security.evidence`), 5
  archivos por campo y 1 firma.
- **EXIF:** se extraen fecha de captura y GPS a columnas **antes** de sanear;
  después la imagen se **reescribe desde sus píxeles** (`ImageSanitizer`), lo
  que borra todo el metadato, desactiva polyglots y aplica la orientación. El
  **SHA-256 es del archivo guardado**, no del recibido.
- **Ubicación:** la del EXIF manda; si no hay (muchos navegadores móviles la
  quitan), la del dispositivo al entregar (`navigator.geolocation`). Se guarda
  `location_source` y la **distancia a la sede**. La ficha señala fotos a más de
  500 m y tomadas más de 24 h antes de entregar.
- **Firma:** canvas Alpine (`resources/js/evidence.js`, sin scripts en línea por
  la CSP) → PNG → misma tubería, con IP y hora del servidor. **Desviación del
  plan:** no hay tabla `signatures`; la firma es un `attachment` con
  `kind = signature` (documentado en la migración).
- **Transacción + compensación:** `SubmitReport` guarda envío, adjuntos y
  obligación en una transacción. El bucket no participa: si algo falla después
  de subir, `DiscardEvidence` borra lo subido. Probado con una segunda foto
  inválida.
- **URL firmada servida por la app** (`/evidencia/{id}`, middleware `signed`,
  5 min), no URL prefirmada del bucket: la firma lleva el dominio del tenant, el
  bucket no tiene que ser alcanzable desde fuera y la **Policy se evalúa al
  firmar y otra vez al servir**. Exige sesión. `nosniff`, `no-store`, solo las
  imágenes inline, y cabecera `X-Evidence-SHA256`.
- **Policies:** `SubmissionPolicy` (ver envíos o ser su autor, y alcanzar la
  sede) y `AttachmentPolicy` (`evidence.view` o haberlo subido, y poder ver el
  envío).
- **Ficha del envío** `/envios/{id}`: respuestas leídas con la versión con la que
  se respondió, evidencia con hash, distancia, hora de captura y origen de la
  ubicación. Tras entregar se redirige ahí (antes, a pendientes).
- Las subidas temporales de Livewire van al disco **local** (con s3 subían
  directo a `minio:9000`, inalcanzable desde el navegador, y sin validar).
- Pruebas: fotos con EXIF construidas byte a byte (`tests/Support/EvidenceFixtures`).
  Se sondeó quitando la firma de la ruta, la Policy al servir, la compensación y
  el saneado: cada una rompe sus pruebas. §7.5 cubierto: la URL de A se rechaza
  en B **aunque B tenga un adjunto con el mismo id**.

### `Scheduling` — pantalla de programaciones

- **Nadie escribe RRULE a mano.** `RecurrencePattern` traduce diaria / semanal
  (con días) / mensual (día fijo o último día) con intervalo a la regla, y de
  vuelta al abrir para editar. Lo que no reconoce (BYSETPOS, COUNT, «primer
  lunes») lo deja como **regla personalizada, sin reinterpretarla**: reescribir
  una regla que no se entiende del todo cambiaría las fechas en silencio.
- El formulario muestra las **próximas fechas** mientras se edita (sin descontar
  feriados; lo avisa).
- **Replanificación (`ReplanObligations`).** Las obligaciones siguen siendo una
  foto para lo que ya es historia: lo cumplido, incumplido, excusado y lo que
  **ya abrió** no se toca. Lo `pending` que **todavía no abrió** es un plan: al
  editar, pausar o reanudar se descarta y se vuelve a materializar sobre el
  horizonte del job. Sin esto, pausar seguía pidiendo dos semanas de entregas.
- **Nada nace vencido.** Crear o cambiar una programación a media tarde no crea
  la ocurrencia cuyo cierre ya pasó (`MaterializeObligations` acepta
  `notClosedBefore`). El job horario no lo usa (ver puntos abiertos).
- `LaunchSchedule` = crear + materializar en el acto, en una transacción: quien
  programa el arqueo de hoy lo ve en pendientes sin esperar al job.
  `CreateSchedule` sigue siendo la pieza base (solo escribe).
- **La plantilla de una programación no cambia**: su historial de cumplimiento
  es de esa plantilla. Para pedir otra, otra programación.
- Validación compartida en `ValidateSchedule` (crear y editar no pueden
  divergir).
- **Frontera por sede:** con alcance «lista de sedes», solo se aceptan sedes que
  el usuario alcanza (`AssignedSitesScope`); un id ajeno da error de formulario.
- `SchedulePolicy`: ver con `schedule.view`; crear, editar, pausar y reanudar con
  `schedule.manage`.
- Faltaban `lang/*/schedule-scope.php` (el enum ya los usaba): añadidos, junto
  a `recurrence-frequency` y `weekdays`.

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

### `Submissions` — la entrega (ADR 0012)

- `SubmitReport` escribe **tres tablas en una transacción**: el envío con la
  respuesta completa, la copia tipada de lo reportable y la obligación, que pasa
  a `fulfilled`. Hay un test que manda respuestas inválidas y comprueba que no
  queda ni envío, ni valores, ni obligación cumplida.
- El envío guarda **la versión con la que se respondió**.
- Importes y números se guardan como **texto decimal, nunca float**, y se
  rechaza la notación científica en vez de perder precisión.
- `value_numeric` es `numeric(18,4)` y no `(14,2)`: recibe importes pero también
  puntajes, y `(14,2)` redondearía un 4,375.
- Un campo oculto por su condición ni se exige ni se guarda; lo que no pertenece
  al formulario se descarta.
- La entrega se rechaza **pasado el cierre por la hora**, no solo por el estado:
  entre el cierre y la siguiente pasada del job la obligación sigue `pending`.
- Doble barrera contra cumplir dos veces: bloqueo de fila en la Action y
  restricción única en `submissions.obligation_id`.
- **Frontera por sede en la entrega:** `ObligationPolicy` exige el permiso y
  alcanzar la sede, reutilizando `SitePolicy`. Sin eso, cambiar un id en la URL
  cumpliría la entrega de otro local.
- Flujo del §9.4 declarado entero en la máquina de estados; hoy solo se usa la
  entrada en `submitted`. Las transiciones de revisión llegan con `Workflow`.

**Límites conocidos de la entrega:**

- **La tabla de filas no se puede completar** (necesita un editor propio). Si
  es obligatoria y visible, el envío se rechaza con un mensaje que lo dice.
- **Los campos calculados no se calculan**: no hay motor de fórmulas.
- Sin borrador en servidor: el plan (§13.3) lo pone en el dispositivo.

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

- **Evidence, pendiente del §9.5/§10.4:** marca de agua opcional, antivirus
  (ClamAV) con cuarentena, geoetiqueta *obligatoria* por campo (el diseñador no
  tiene la opción), cuota por plan, retención/purga y registro en
  `audit_trail` de cada acceso. Las orientaciones EXIF 5 y 7 (espejadas) se
  aproximan.
- **La evidencia se sirve a través de la app.** Con mucho volumen conviene
  servir desde R2 con URL prefirmada tras autorizar; hoy se prefirió la
  garantía de dominio y Policy por acceso.
- **Zonas y cargos no tienen pantalla.** El formulario de sede ofrece zonas pero
  no se pueden crear desde la interfaz.
- **El job horario puede crear obligaciones ya cerradas** para una sede dada
  de alta a media tarde (el día en curso), que en la pasada siguiente quedan
  `missed`. La pantalla ya lo evita con `notClosedBefore`; aplicarlo también al
  job cambia el contrato de `ObligationMaterializationTest` (materializa rangos
  pasados a propósito), así que se dejó para decidirlo aparte.
- **Una programación cuya plantilla se archive no se puede editar** (la
  validación exige plantilla publicada); sí se puede pausar.
- **`last_login_at` nadie la escribe.** Falta un listener del evento `Login`.
- **DTOs vs spatie/laravel-data.** PHP no deja que una clase `readonly` extienda
  `Data`, y la regla de arquitectura exige DTOs readonly. Decidir antes de atar
  un DTO a un `Request`.
- **Ventanas que cruzan la medianoche** no se admiten. Si un cliente lo necesita
  (turno de noche), hay que modelarlo explícitamente.

---

## Lo que queda del plan, en orden

1. ~~Pantalla de programaciones.~~ Hecha.
2. ~~`Evidence`.~~ Hecho (con los pendientes de arriba).
3. **`Workflow`** — revisión y aprobación (§9.4). La máquina de estados ya
   está declarada; faltan las Actions y la bandeja del supervisor.
4. **`Notifications`** — recordatorios y escalamiento.
5. **`Insights`** — KPI de cumplimiento: `fulfilled / (fulfilled + missed)`.
6. **`Api`**, Sentry/Pulse, provisión asíncrona con progreso.

---

## Cómo retomar

```bash
cd c:/proyecto/ronda
docker compose up -d                                  # minio-init crea el bucket y sale
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
