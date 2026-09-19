# Dónde nos quedamos — 22 de septiembre de 2026

Estado del proyecto: **fase 0 cerrada; fase 1 con el motor completo de punta a
punta: se programa, se entrega con evidencia, se revisa, el sistema avisa y
escala solo, y el panel mide**.
El plan completo está en `../../RONDA-PLAN-MAESTRO.md`, documento interno que
no forma parte de este repositorio (el repositorio es público).

---

## Resumen en una línea

Se diseñan plantillas, se programan **desde la pantalla**, el motor materializa
las obligaciones, y el encargado ve su lista de pendientes de hoy y entrega el
reporte **con fotos, archivos y firma**, que cumple la obligación. El
supervisor lo **aprueba o lo rechaza** con motivo, y la sede lo corrige. Por el
camino el sistema **recuerda, avisa y escala** solo, y el panel **mide** el
cumplimiento, la puntualidad, la calidad y el tiempo de revisión.

```
PLANTILLA ✅ → PROGRAMACIÓN ✅ → OBLIGACIÓN ✅ → ENVÍO ✅ → FLUJO ✅ → KPI ✅
```

---

## Por dónde seguir (acordado con el cliente)

**Siguiente: terminar la captura offline** — hoy la PWA se instala, sobrevive
sin señal y guarda el borrador en el dispositivo, pero **entregar todavía exige
red**. Falta la cola de envío (outbox) con sus fotos, que es la otra mitad del
§13.3. Después va la fase 2 (onboarding self-service, planes y límites,
facturación, back-office, sitio público) y solo entonces la API, que es fase 3.

Avance sobre el alcance del plan, ponderado por las semanas que estima cada
fase: fase 0 **100 %**, fase 1 **~90 %**, fase 2 **~15 %**, fases 3 a 5 sin
empezar. Para tener un piloto operando falta poco; para vender solo, bastante
más, y casi todo lo que falta ahí no es código.

**Lo que no puede hacer el código y conviene empezar ya:** el trámite de
WhatsApp Business con Meta. El plan lo pone en la fase 1 justamente porque
tarda, y el aviso por WhatsApp es donde ya vive la operación de los clientes.

---

## Lo que funciona, verificado ejecutándolo

Todo junto: `docker compose exec app composer check` (pint + phpstan + rector +
deptrac + pest). La suite tarda **entre 7 y 14 minutos** según la carga de la máquina (por eso `composer.json` fija `process-timeout: 0`; con el límite de 300 s de Composer, `check` se cortaba): la suite `Integration`
provisiona tenants reales en cada prueba. Para iterar, `--filter`.

| Módulo | Qué hace | Pantallas |
|---|---|---|
| `Platform` | Provisión de tenant con base propia, aislamiento de sesión | — |
| `Identity` | Usuarios, roles, permisos, 2FA, invariantes del propietario | `/usuarios` |
| `Directory` | Zonas, sedes, cargos, asignación persona ↔ sede, frontera por sede | `/sedes`, `/zonas`, `/cargos`, `/usuarios/{id}/sedes` |
| `Forms` | Plantillas versionadas, diseñador visual, publicación, catálogo de arranque | `/plantillas`, `/plantillas/catalogo` |
| `Scheduling` | Programaciones RRULE, feriados, materialización y replanificación de obligaciones | `/programaciones` |
| `Submissions` | Entrega de reportes, validación contra la versión, réplica reportable, ficha del envío | `/pendientes`, `/envios/{id}` |
| `Workflow` | Revisión: tomar, aprobar, rechazar, corregir; historial, comentarios y revisiones anteriores | `/revision`, `/envios/{id}/corregir` |
| `Notifications` | Recordatorios, escalamiento por SLA, campana in-app y correo | `/notificaciones` |
| `Evidence` | Fotos, archivos y firma en bucket privado; SHA-256, EXIF, distancia a la sede, URL firmada | `/evidencia/{id}` (firmada) |
| `Insights` | KPI materializados y exportación de envíos a Excel, en cola | `/panel`, `/exportaciones` |

---

## Lo que se hizo en las últimas sesiones

### PWA instalable y borrador en el dispositivo (§13.3, ADR 0010)

El usuario diario es un encargado con un teléfono de gama media y señal
irregular; el plan marca su adopción como el riesgo número uno del producto.

- **Instalable**: `public/manifest.webmanifest` (arranca en `/pendientes`,
  modo `standalone`) con iconos propios, incluido uno `maskable` para el
  recorte de Android. El manifiesto va **también en el login**, que es la
  primera pantalla que ve el encargado y desde donde va a instalarla.
- **Service worker** (`public/sw.js`): guarda el bundle y los iconos al usarlos,
  vuelve a servir las pantallas ya visitadas cuando no hay red, y cae en
  `public/offline.html` (que se explica sola, sin bundle ni servidor) para lo
  que nunca se abrió.
- **Nada privado se queda en el teléfono**: no se guarda nada que no sea GET, ni
  el endpoint de Livewire, ni la evidencia, ni las descargas de exportaciones.
  Al cerrar sesión la página avisa al service worker y se borra la caché de
  pantallas: un teléfono compartido no puede seguir mostrando los pendientes de
  quien se fue.
- **Borrador en IndexedDB** (como pide el §13.3) en el formulario de entrega y
  en el de corrección: se guarda mientras se escribe y se restaura al volver,
  con un aviso. Se borra cuando el envío ya está guardado en el servidor. Si
  IndexedDB falla (modo privado, cuota), se sigue pudiendo entregar: un borrador
  es una comodidad, no un requisito.
- **Aviso de sin conexión** en el layout, antes de que alguien intente entregar
  y se quede mirando una rueda girando.
- **Lo que las pruebas NO cubren**, y hay que mirar a mano en un móvil: que el
  navegador ofrezca instalar, que el borrador vuelva tras cerrar la pestaña y
  que una pantalla vieja se sirva sin red. Lo que sí se sostiene en CI es el
  contrato: piezas presentes, textos, y que el service worker no cachee nada
  privado.
- **Falta la otra mitad**: la cola de envío offline (entregar sin señal y que
  salga solo al volver), con sus fotos en IndexedDB. Hoy entregar exige red.

### `Insights` — exportación de envíos (§13)

- **Nunca en la petición**: pedirla solo anota el encargo y encola el trabajo.
  El archivo lo escribe un job y quien lo pidió recibe un aviso al terminar
  (también si falla, con el motivo).
- **Cola propia `reports`** (§13: colas separadas por criticidad), con
  `config/horizon.php` publicado y un supervisor aparte: memoria más alta,
  `timeout` de 15 minutos y prioridad de CPU más baja. Una exportación pesada no
  puede retrasar un aviso de SLA.
- **Streaming de punta a punta** con `openspout` (no `maatwebsite/excel`: para
  una hoja tabular es un envoltorio de más): la consulta se recorre por lotes y
  el XLSX se escribe según llegan las filas. Primero a un archivo temporal, y al
  bucket solo al final: un archivo a medias en el bucket parece terminado.
- **La frontera por sede se congela al encargarla.** El job corre sin sesión, así
  que las sedes visibles se guardan en los filtros. De paso, un cambio de
  permisos entre el encargo y la ejecución no ensancha lo exportado.
- Columnas fijas (día de la obligación, sede, plantilla, versión, estado,
  puntualidad, autor, revisor, decisión) y, **si se filtra por una plantilla**,
  sus campos reportables como columnas. Sin filtro no se mezclan: dos plantillas
  no comparten campos.
- **Solo la descarga quien la pidió**, y el archivo sale por la aplicación, no
  por una URL del bucket.
- Sondeado quitando la frontera congelada, el dueño de la descarga y la cola
  aparte.
- **Ojo en pruebas:** con `Queue::fake()`, `dispatch_sync` también se intercepta
  (Laravel lo manda a la conexión `sync`, que está fingida) y el job no corre.
  Las pruebas llaman a `handle` por el contenedor.

### `Forms` — el catálogo de arranque (§3.5)

Las cinco plantillas del plan, listas para instalar: arqueo de caja, depósito
bancario, apertura y cierre, reporte de incidencias y checklist de limpieza.

- **Son configuración del motor, no código**: cada una es un esquema de campos
  de los mismos tipos que ofrece el diseñador. Instalar deja una plantilla
  **normal y publicada**, que se edita, versiona y programa como cualquier otra;
  nada del resto del sistema sabe que vino del catálogo.
- **No se instalan solas** al crear el cliente: cada uno pide unas cosas y no
  otras, y un catálogo entero volcado en la lista es ruido que hay que borrar.
  Se instalan desde `/plantillas/catalogo`.
- **Instalar dos veces no duplica ni pisa** lo que el cliente haya cambiado: si
  ya existe el código, se devuelve esa plantilla y no se publica versión nueva.
- Instalar pide `template.publish`, el mismo permiso que publicar: es lo que
  empieza a exigir entregas a las sedes.
- Usan de verdad lo que el motor ya sabe hacer: condiciones de visibilidad (el
  motivo de la diferencia solo si la hay; la alarma solo al cerrar), foto y
  firma obligatorias, y campos reportables para el KPI.
- Una prueba construye los cinco esquemas con las mismas reglas que los del
  cliente: si un catálogo se rompe (una condición a un campo inexistente, una
  clave repetida), la CI lo para.

### `Directory` — zonas y cargos con pantalla (§8.3)

Era el hueco que impedía que un cliente montara su propia estructura: las zonas
solo se podían sembrar por código, y programar «todas las sedes de una zona»
era inusable desde la interfaz.

- **`/zonas`**: listado, alta y edición, con jerarquía (una zona cuelga de otra)
  y responsable informativo. **Una zona no puede colgar de sí misma ni de una de
  sus hijas**: sería un ciclo, y recorrer el árbol dejaría de terminar. Lo
  impide la Action, porque la base no puede.
- **`/cargos`**: lista y alta en la misma pantalla (un cargo son dos campos y se
  crean de cinco en cinco al montar el cliente).
- **Borrar comprueba antes lo que cuelga** y dice qué mover, en vez de dejar
  reventar la restricción de PostgreSQL: una zona con sedes, con subzonas o
  **usada en una programación** no se borra. Esto último la base no lo impide
  (el borrado es lógico) y habría dejado la programación sin materializar en
  silencio. Un cargo que alguien ocupa, tampoco.
- Permisos: las zonas se gobiernan con los de sedes (`site.view` /
  `site.manage`); los cargos, con los de personas (`user.view` /
  `user.manage`), que es quien organiza el organigrama.
- El menú agrupa Sedes, Zonas y Cargos bajo **Estructura**.
- Sondeado quitando: el guardia del ciclo, la comprobación de programaciones, la
  de asignaciones y el permiso de ver cargos.

### `Insights` — los KPI (§9.6)

- **Tabla `kpi_daily`**, materializada por job: día de la sede × sede ×
  plantilla. **Ninguna pantalla agrega sobre `submissions` en tiempo real**
  (§13); el panel solo suma filas ya calculadas.
- **Se recalcula, no se acumula**: una aprobación, una corrección o una
  justificación cambian cifras de días ya cerrados. El job repasa una ventana de
  7 días cada hora (`kpi:recalculate`, a las :20) y borrar + reescribir el rango
  en una transacción es idempotente.
- **Cuatro indicadores** en `KpiSummary` (puro y probado aparte):
  cumplimiento `fulfilled / (fulfilled + missed)` — lo **justificado no cuenta**,
  ni a favor ni en contra—, puntualidad (y minutos de retraso promedio **solo
  sobre lo tardío**), calidad (aprobado a la primera sobre lo ya decidido) y
  tiempo de revisión.
- **Un indicador sin base es `null`, no cero**, y la pantalla lo pinta con un
  guion: «no había nada que medir» y «se incumplió todo» no son lo mismo.
- **Ranking de sedes por el peor cumplimiento primero** y **reincidencia** (misma
  sede, misma plantilla, 3 o más incumplimientos en el periodo).
- **Frontera por sede** dentro de la consulta base: un supervisor ve solo sus
  sedes; quien no tiene `report.view` sigue viendo la bienvenida y su acceso a
  pendientes, no un muro.
- Los envíos se atribuyen **al día de la obligación**, no al día en que se
  entregaron: un arqueo del martes entregado a las 00:10 del miércoles sigue
  siendo del martes.
- **Desviaciones del plan**, documentadas en la migración: no hay `kpi_weekly`
  ni `kpi_monthly` (una semana es una suma de filas con índice), y el tiempo de
  revisión se guarda como suma y cuenta en vez de mediana, porque una mediana no
  se puede sumar entre días.
- Sondeado quitando: los justificados fuera del cumplimiento, la frontera por
  sede, el borrado previo al recálculo y el «a la primera».

### `Notifications` — recordatorios y escalamiento (§9.3 y §9.4)

- **Seis avisos**: entrega que vence pronto, entrega no realizada, envío que
  llega a la bandeja, revisión atrasada, envío aprobado y envío rechazado (con
  el motivo dentro, para no tener que abrir la aplicación para saber qué
  corregir).
- **Canales por tema** en `config/notifications.php`: campana in-app y correo.
  **WhatsApp no está**: se añade como canal sin tocar Actions ni oyentes, porque
  la entrega vive detrás de `OperationalMessenger`.
- **Escalera configurable** (`EscalationLadder`, pura y probada aparte): lo
  incumplido avisa a la sede al momento, a quien revisa a las 2 h y a quien
  administra al día siguiente; una revisión parada reclama a las 24 h y escala a
  las 48 h. Si el job estuvo parado salen todos los peldaños vencidos, cada uno
  con su nivel.
- **Nada se repite**: `NotifyOnce` anota en `sla_events` (restricción única por
  asunto, tema y nivel) **antes** de mandar. El job horario puede repasar el
  parque entero sin volver a avisar. Un envío corregido sí puede volver a
  escalar: su nivel lleva sumada la revisión.
- **A quien puede hacer algo**: los destinatarios se resuelven preguntando a las
  Policies persona a persona (regla 4), no consultando roles. Nadie recibe por
  correo algo que no podría abrir.
- **Las URL llevan el dominio del cliente** (`TenantUrlQuery`): en un job no hay
  petición, y `route()` habría enlazado al dominio central.
- **Campana en la cabecera** (se refresca sola cada minuto) y `/notificaciones`
  con «solo sin leer» y marcar como leídas.
- Comando `notifications:sla`, un job por tenant, programado cada hora **diez
  minutos después** de `obligations:materialize`: primero se marca lo
  incumplido, luego se avisa.
- **Arquitectura:** `Application` no conoce `Infrastructure`, así que la entrega
  pasa por el contrato `OperationalMessenger` y los oyentes se enchufan en
  `NotificationsEventServiceProvider`, que vive en `Infrastructure` (deptrac
  rechazó las dos versiones anteriores).
- `SubmitReport` ahora lanza `SubmissionSubmitted` (§6.2), del que cuelga el
  aviso a quien revisa.
- Verificado a mano contra Mailpit: el correo llega con su asunto y el aviso
  queda en la campana. Sondeado quitando la anotación previa, el filtro de
  destinatarios, la ventana de apertura y el desfase por revisión.

### `Workflow` — revisión y aprobación (§9.4)

- **El flujo del plan, fijo en código** (`SubmissionState`): enviado → en
  revisión → aprobado (final) o rechazado → corregir → enviado. **No hay flujos
  configurables por plantilla** (`workflows`, `workflow_states`,
  `workflow_transitions`): se crearán cuando haya un diseñador que los use.
- **Tomar reserva la decisión**: mientras está en revisión solo decide quien lo
  tomó (con bloqueo de fila). Aprobar y rechazar lo toman de paso, así que no
  hay un clic de más, pero el historial registra las dos transiciones.
- **Nadie revisa lo que entregó**, tampoco la propietaria: `OwnerGate` salta las
  Policies, pero la separación de funciones la aplica la Action.
- **Rechazar exige motivo** y se comprueba antes de tomar: un rechazo sin motivo
  no deja el envío reservado.
- **Aprobar es un permiso aparte** (`submission.approve`) de revisar/rechazar
  (`submission.review`). Todo exige además alcanzar la sede.
- **Corregir** (`CorrectSubmission`): valida contra la **versión con la que se
  entregó**, guarda lo rechazado en `submission_revisions` con el motivo,
  reescribe `data` y la réplica reportable juntas y vuelve a `submitted`. La
  evidencia no reemplazada se conserva; la reemplazada sigue en `attachments`
  (la referencia la revisión) y la ficha muestra solo la vigente.
  `submitted_at` y el retraso no cambian.
- **Rechazar no deshace el cumplimiento** de la obligación: se entregó, y la
  calidad se medirá aparte.
- **Desviación del plan:** en lugar de `reviews` hay `submission_transitions`
  (cada cambio de estado con actor, hora y comentario, incluida la entrega).
  Además `submission_comments` y `submission_revisions`.
- Eventos `SubmissionTaken/Approved/Rejected/Corrected` con
  `ShouldDispatchAfterCommit`; `Notifications` ya escucha los de aprobación y
  rechazo.
- **Pantallas:** bandeja `/revision` (por estado, «solo los míos», búsqueda,
  frontera por sede), panel en la ficha del envío (decidir, historial,
  comentarios), `/envios/{id}/corregir` y, en pendientes, «Rechazados, por
  corregir». Índice parcial `WHERE state = 'submitted'` (§8.5).
- Refactor: la evidencia de las respuestas pasa por `StoreAnswerEvidence`
  (entrega y corrección iguales); los campos del formulario son un parcial
  (`submissions::partials.fields`) y la recogida de archivos un trait
  (`CollectsEvidence`).
- Sondeado quitando: no revisar lo propio, no decidir lo tomado por otro,
  guardar la revisión anterior y el permiso de aprobar.

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

- **Insights, pendiente:** gráficos de evolución (hoy son tablas y cifras),
  exportación a **PDF** (la de Excel ya está) e informes programados por correo,
  `kpi_weekly`/`kpi_monthly` si el volumen lo pide, puntaje compuesto del
  ranking y KPI por zona (hoy por sede y plantilla). La exportación tampoco
  tiene todavía límite por plan ni purga de archivos viejos.
- **Notifications, pendiente:** WhatsApp (§5.2; el trámite con Meta es del
  plan de fase 1), preferencias por persona y por tema (hoy los canales son del
  sistema), plazos de SLA por plantilla (`sla_policies`) en vez de una escalera
  global, calendario laboral aplicado a los plazos, y push web (§13).
- **Workflow, pendiente:** SLA de revisión y escalamiento (van con
  `Notifications`), liberar o reasignar un envío tomado (hoy nadie puede
  quitárselo a quien lo tomó), ver en pantalla las respuestas de revisiones
  anteriores (se guardan pero no se muestran) y flujos configurables.
- **Evidence, pendiente del §9.5/§10.4:** marca de agua opcional, antivirus
  (ClamAV) con cuarentena, geoetiqueta *obligatoria* por campo (el diseñador no
  tiene la opción), cuota por plan, retención/purga y registro en
  `audit_trail` de cada acceso. Las orientaciones EXIF 5 y 7 (espejadas) se
  aproximan.
- **La evidencia se sirve a través de la app.** Con mucho volumen conviene
  servir desde R2 con URL prefirmada tras autorizar; hoy se prefirió la
  garantía de dominio y Policy por acceso.
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

Hecho desde el último corte: pantalla de programaciones, `Evidence`,
`Workflow`, `Notifications`, `Insights` (KPI), zonas y cargos con pantalla, el
catálogo de las 5 plantillas y la exportación a Excel en cola. Todo con sus
pendientes anotados arriba.

Lo que queda, en el orden acordado:

1. **PWA con captura offline** (§13.3): borrador en el dispositivo, cola de
   envío y evidencia diferida. Es el riesgo de adopción número uno.
2. **WhatsApp** en `Notifications`, en cuanto Meta apruebe el trámite.
3. **Fase 2 — producto vendible**: onboarding self-service, planes y límites
   (`laravel/pennant`), facturación, back-office con impersonación auditada,
   sitio público y precios.
4. **Fase 3 — apertura**: API pública v1 con OpenAPI, webhooks, importadores
   (CSV de sedes y usuarios), integraciones e informes programados.
5. **Fase 4 — migrar TriMax** como tenant: es la prueba de fuego del motor.
6. **Fase 5 — endurecer y GA**: pentest, carga con k6, ensayo de recuperación,
   ANPD y documentación de usuario.

Transversal y aún sin empezar: `audit_trail` encadenada por hash (§13),
Sentry/Pulse y la provisión asíncrona con progreso.

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
Los tres repasos que mueven el sistema, si hace falta lanzarlos a mano:

```bash
docker compose exec app php artisan obligations:materialize --sync  # obligaciones
docker compose exec app php artisan notifications:sla --sync        # avisos y escalamiento
docker compose exec app php artisan kpi:recalculate --sync          # KPI del panel
```

En marcha normal los ejecuta el contenedor `scheduler` cada hora (a las :00,
:10 y :20). Las exportaciones van por la cola `reports`, que atiende Horizon.

- Correo de desarrollo (Mailpit): http://localhost:8025
- Bucket de evidencia (MinIO): http://localhost:9001

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
