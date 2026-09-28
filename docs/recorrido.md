# Recorrido por Ronda

Para sentarse delante del sistema y entender qué es, quién ve qué y por qué.
No es documentación de usuario final: es el mapa para recorrerlo tú mismo.

---

## Qué es Ronda, en una frase

**Ronda convierte "hay que revisar los locales" en obligaciones con fecha,
responsable y prueba.**

El problema que resuelve no es el formulario. Es que hoy, en una empresa con
veinte locales, nadie puede responder tres preguntas sin llamar por teléfono:

- ¿Se hizo el arqueo de ayer en Miraflores?
- Si se hizo, ¿quién lo hizo y qué encontró?
- Si no se hizo, ¿alguien se enteró a tiempo?

Un grupo de WhatsApp con fotos no responde ninguna. Ronda responde las tres
porque **cada tarea esperada existe como fila en la base antes de que pase la
hora**: si no se entrega, no es una ausencia que alguien deba notar, es un
incumplimiento que el sistema marca solo y escala solo.

---

## El modelo mental (seis palabras, en orden)

```
PLANTILLA → PROGRAMACIÓN → OBLIGACIÓN → ENVÍO → REVISIÓN → KPI
```

| Palabra          | Qué es                                                              | Quién la toca          |
| ---------------- | ------------------------------------------------------------------- | ---------------------- |
| **Plantilla**    | El formulario: qué se pregunta y qué evidencia se pide               | Administrador          |
| **Programación** | Cuándo y dónde: «arqueo diario en todas las sedes, de 8:00 a 22:00» | Administrador          |
| **Obligación**   | Una fila por sede y por día: la tarea concreta que toca hoy          | La crea el sistema     |
| **Envío**        | El reporte entregado, con fotos y firma, que cumple la obligación    | Encargado de sede      |
| **Revisión**     | Aprobado, o devuelto con motivo para que lo corrijan                 | Supervisor             |
| **KPI**          | Cumplimiento, puntualidad, calidad y tiempo de revisión              | Lo calcula el sistema  |

Lo importante de la tercera fila: **la obligación se materializa por
adelantado**. Por eso Ronda puede avisar antes de que venza, marcar el
incumplimiento a la hora exacta y escalar al jefe sin que nadie lo pida.

---

## Los cuatro roles

Ronda no pregunta "¿eres admin?" en ninguna pantalla: pregunta por permisos.
Los roles son cuatro paquetes de permisos que trae cada cuenta nueva, y el
cliente puede reorganizarlos.

| Rol              | Para quién                        | Qué puede                                                    |
| ---------------- | --------------------------------- | ------------------------------------------------------------ |
| **Propietario**  | Quien contrató Ronda              | Todo, incluido plan y facturación. Solo él edita su ficha     |
| **Administrador**| Jefe de operaciones               | Personas, sedes, plantillas, programaciones. **No** facturación |
| **Supervisor**   | Quien controla varias sedes       | Ve y **revisa**: aprueba o devuelve. No crea estructura       |
| **Encargado**    | Responsable de un local           | Ve **sus** sedes y **entrega** reportes con evidencia          |

La regla que lo atraviesa todo se llama **frontera por sede**: un encargado no
ve, ni por URL directa ni por la API, nada de una sede que no tiene asignada.
No es un filtro de pantalla, va pegado al modelo.

---

## Antes de empezar

```bash
cd c:/proyecto/ronda
docker compose up -d
docker compose ps          # los ocho en healthy
```

| Qué                       | Dónde                            | Acceso                                          |
| ------------------------- | -------------------------------- | ----------------------------------------------- |
| Cliente de demostración   | http://demo.localhost:8000       | `owner@demo.test` / `password-de-desarrollo`    |
| Sitio público             | http://localhost:8000            | sin login, a propósito                          |
| Back-office (tú, soporte) | http://localhost:8000/soporte/acceso | `soporte@ronda.pe` / `password-de-desarrollo` |
| Correos de prueba         | http://localhost:8025 (Mailpit)  | —                                               |
| Colas y trabajos          | http://localhost:8000/horizon    | —                                               |
| API pública               | http://demo.localhost:8000/docs/api | —                                            |

Si el tenant demo no existe todavía: `docker compose exec app php artisan db:seed`.

---

## Parte 1 — Eres el dueño y acabas de entrar

Entra a http://demo.localhost:8000 con la cuenta demo. Caes en **`/bienvenida`**,
el asistente de arranque, que tiene cinco pasos en este orden:

1. **Plantillas** — `/plantillas/catalogo` trae plantillas listas (arqueo,
   apertura, limpieza). Elige una en vez de diseñarla: la idea es llegar al
   primer reporte hoy, no mañana.
2. **Sedes** — `/sedes`. Una a mano, o muchas de golpe con **Importar**
   (ver [importar.md](importar.md)).
3. **Equipo** — `/usuarios`. Crea a alguien con rol **Encargado** y asígnale
   una sede (`/usuarios/{id}/sedes`). Anota el correo: lo vas a usar en la
   parte 2.
4. **Programaciones** — `/programaciones/nueva`. Aquí se decide **cuándo**:
   frecuencia (diaria, semanal, días concretos), ventana horaria y a qué sedes
   aplica.
5. **Primer reporte** — el asistente no se da por terminado hasta que exista
   uno de verdad.

> **Detalle que conviene mirar**: al publicar una plantilla se crea una
> **versión**. Los envíos quedan atados a la versión con la que se llenaron,
> así que cambiar el formulario mañana no reescribe la historia de ayer.

Después del wizard, la estructura vive en:

- `/zonas` — agrupan sedes (Lima Norte, Provincia). Sirven para filtrar y para
  que un supervisor cubra un conjunto.
- `/cargos` — el organigrama: qué puesto ocupa cada persona en cada sede.

---

## Parte 2 — Eres el encargado del local

La forma honesta de verlo es **entrar con su cuenta**, no con la del dueño.
Abre una ventana de incógnito en http://demo.localhost:8000 y entra con el
encargado que creaste.

Verás un sistema mucho más pequeño, y eso es lo correcto:

- **`/pendientes`** es su pantalla. Lo que le toca hoy, nada más.
- Abre una obligación y llena el reporte: respuestas, **fotos**, archivos y
  **firma**. La evidencia guarda su SHA-256, los datos EXIF y la distancia a la
  sede.
- Al entregar, la obligación queda cumplida y el envío pasa a revisión.

Tres cosas para probar aquí:

1. **La frontera por sede.** Copia la URL de una sede que NO tenga asignada
   (`/sedes/{otra}/editar`) y pégala en su sesión: recibe un 404, no un "no
   tienes permiso". No se le confirma ni que exista.
2. **El borrador.** Llena medio formulario, cierra la pestaña y vuelve: sigue ahí.
3. **Sin señal.** Ronda es una PWA: se instala, y si se entrega un reporte sin
   red, queda en la cola de envío y sale solo al volver la señal. (Esto es lo
   que falta comprobar en un teléfono de verdad.)

> **Si `/pendientes` sale vacío**: es que todavía no hay obligaciones
> materializadas para hoy. El motor corre cada hora; para no esperar:
> ```bash
> docker compose exec app php artisan obligations:materialize --sync
> ```

---

## Parte 3 — Eres el supervisor

Crea una tercera persona con rol **Supervisor**, o cámbiale el rol al
encargado un momento. Su pantalla es **`/revision`**.

- **Tomar** un envío: queda marcado como que lo está mirando alguien, para que
  dos supervisores no revisen lo mismo.
- **Aprobar**, o **devolver con motivo**. El motivo es obligatorio: un rechazo
  sin explicación obliga a una llamada telefónica, que es justo lo que Ronda
  viene a quitar.
- Si lo devuelve, la sede lo corrige en `/envios/{id}/corregir` y vuelve a
  revisión. En la ficha del envío queda **el historial completo**: quién,
  cuándo, qué dijo y cómo estaba antes.

---

## Parte 4 — Lo que hace el sistema cuando nadie mira

Esta es la parte que no se ve navegando, y es la mitad del producto.

| Cuándo         | Qué pasa                                                        |
| -------------- | --------------------------------------------------------------- |
| Cada hora      | Se materializan las obligaciones de los próximos días            |
| Cada hora      | Se cierra lo vencido: lo no entregado pasa a **incumplido**      |
| 10 min después | Repaso de SLA: recordatorios antes de vencer, escalamiento después |
| Cada hora      | Se recalculan los KPI                                            |

Para verlo sin esperar a que pase la hora:

```bash
docker compose exec app php artisan obligations:materialize --sync
docker compose exec app php artisan notifications:sla --sync
docker compose exec app php artisan kpi:recalculate --sync
```

Y ahora mira las tres salidas:

- **La campana** arriba a la derecha, y `/notificaciones`.
- **Mailpit** (http://localhost:8025): ahí están los correos que se mandaron.
- **`/panel`**: cumplimiento, puntualidad, tasa de rechazo y tiempo de
  revisión. Los KPI salen de una tabla materializada, no de contar envíos en
  vivo: por eso el panel no se pone lento cuando hay cien mil.

> **Experimento que explica el producto entero**: crea una programación con
> ventana que ya pasó, materializa, no entregues nada y corre el repaso de SLA.
> Verás la obligación en **incumplida**, el aviso al encargado, y el
> escalamiento al supervisor. Nadie tuvo que darse cuenta de nada.

---

## Parte 5 — Eres el administrador de la cuenta

| Pantalla                  | Para qué                                                                 |
| ------------------------- | ------------------------------------------------------------------------ |
| `/plan`                   | Qué plan tiene, qué consume y qué límites le aplican                     |
| `/exportaciones`          | Envíos a Excel. Va en cola: se pide y se descarga cuando está            |
| `/sedes/importar`, `/usuarios/importar` | Alta masiva desde una hoja de cálculo ([importar.md](importar.md)) |
| `/integraciones`          | Tokens de la API pública, con alcances. Se muestran **una sola vez**     |
| `/integraciones/avisos`   | Webhooks: Ronda llama a tu sistema cuando pasa algo ([webhooks.md](webhooks.md)) |

Cosas que se entienden mejor tocándolas:

- **El plan manda de verdad.** Con plan Starter, `/integraciones` avisa de que
  la API no está incluida, y la API responde 403 aunque el token sea válido.
- **La evidencia nunca es pública.** `/evidencia/{id}` genera una URL firmada
  que dura cinco minutos, y la autorización se evalúa **antes** de firmarla.
  Copia una URL de evidencia, espera, y verás que caduca.

---

## Parte 6 — Eres soporte (esto es tuyo, no del cliente)

El back-office vive en el dominio central, no dentro de ningún cliente:
**http://localhost:8000/soporte/acceso**.

- Cuenta propia (`soporte@ronda.pe`), separada de las cuentas de cliente, y con
  **2FA obligatorio**: la primera vez te pide configurarlo.
- `/soporte/clientes` — la lista de cuentas, su plan y su estado.
- Ficha de un cliente → **Entrar como cliente**. Eso es la **suplantación
  auditada**, y tiene cuatro candados a propósito:
  1. Motivo obligatorio, de verdad (no vale "prueba").
  2. Caduca a la media hora.
  3. Banner permanente mientras dure: nunca olvidas que no eres tú.
  4. Queda registrada, y **se le avisa al cliente en el momento**.

Esa última es la que importa: el día que un cliente pregunte "¿alguien de
ustedes entró a mi cuenta?", la respuesta es una fila con fecha, persona y
motivo, no un "no creo".

---

## Parte 7 — Lo que ve quien todavía no es cliente

- **http://localhost:8000** — la portada.
- **`/precios`** — los planes. **Los precios salen de la tabla `plans`**, no
  del HTML: el número que se publica y el que se cobra son el mismo. El plan
  cotizado se menciona pero no lleva precio, porque no tiene uno de lista.
- **`/registro`** — una empresa se da de alta sola: elige subdominio, espera
  mientras se provisiona **su propia base de datos**, y entra al asistente.

Pruébalo entero: registra `prueba.localhost` y verás el ciclo completo de un
cliente nuevo sin que tú toques nada.

---

## La decisión de arquitectura que explica el resto

**Cada cliente tiene su propia base de datos.** No una columna `tenant_id`
compartida: una base entera.

Se nota en todo: un error de programación no puede filtrar datos de una
empresa a otra, se puede restaurar el respaldo de un cliente sin tocar a los
demás, y el día que uno se vaya, su información se borra de una pieza.

El precio es la operación (migrar N bases en vez de una), y está asumido: en
este negocio, "se vio un dato de otra empresa" no es un fallo, es el final del
contrato.

---

## Mapa rápido de URLs

| Área            | Ruta                                            |
| --------------- | ----------------------------------------------- |
| Panel           | `/panel`                                        |
| Mis pendientes  | `/pendientes`                                   |
| Revisión        | `/revision`                                     |
| Envío           | `/envios/{id}`, `/envios/{id}/corregir`         |
| Sedes           | `/sedes`, `/sedes/importar`                     |
| Zonas y cargos  | `/zonas`, `/cargos`                             |
| Personas        | `/usuarios`, `/usuarios/importar`, `/usuarios/{id}/sedes` |
| Plantillas      | `/plantillas`, `/plantillas/catalogo`, `/plantillas/{id}/disenar` |
| Programaciones  | `/programaciones`                               |
| Notificaciones  | `/notificaciones`                               |
| Exportaciones   | `/exportaciones`                                |
| Plan            | `/plan`                                         |
| Integraciones   | `/integraciones`, `/integraciones/avisos`, `/docs/api` |
| Arranque        | `/bienvenida`                                   |
| Soporte         | `/soporte/acceso`, `/soporte/clientes`          |
| Público         | `/`, `/precios`, `/registro`                    |

---

## Si algo no aparece

| Síntoma                             | Causa casi siempre                                                    |
| ----------------------------------- | --------------------------------------------------------------------- |
| `/pendientes` vacío                 | Faltan obligaciones: `obligations:materialize --sync`                 |
| No llegan correos                   | Los manda el contenedor `horizon`, no `app`. `docker compose ps horizon` |
| El panel en cero                    | `kpi:recalculate --sync`                                              |
| Una pantalla nueva no aparece       | Octane cachea: `docker compose restart app`                           |
| Los estilos se ven rotos            | Falta compilar: `npm run build`                                       |
| Un subdominio da 404                | Es correcto: ese cliente no existe                                     |
