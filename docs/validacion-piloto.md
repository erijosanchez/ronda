# Validación del piloto — 20 de septiembre de 2026

Recorrido completo de un cliente nuevo contra el stack de verdad (PostgreSQL,
MinIO, Redis, Horizon, FrankenPHP), no contra dobles. Lo que las pruebas cubren
en aislamiento, aquí se ejecutó junto.

Los dos guiones estan en `docs/piloto/` y se repiten con:

```bash
docker compose exec -T app php artisan tinker --execute="require '/app/docs/piloto/validar-piloto.php';"
bash docs/piloto/validar-http.sh <subdominio>.localhost
```

Crean un cliente de prueba y borran el de la corrida anterior: **no se ejecutan
contra produccion**.

## Veredicto

**El producto aguanta un piloto.** Todo el recorrido funciona de punta a punta.
Aparecieron **dos fallos reales**, los dos corregidos en esta sesión, y queda
**una cosa que no se puede validar sin un teléfono**.

## Lo que se ejecutó y pasó

| | Comprobado |
|---|---|
| Registro | Alta self-service, base propia creada, migrada y sembrada, **lista en 7–10 s** (el plan pide < 30 s) |
| Comercial | Entra en Starter, se abre la prueba de 14 días, sin tarjeta que cobrar |
| Arranque | Plantilla del catálogo, sede con su zona horaria, encargada invitada y asignada, programación diaria |
| Motor | Obligación materializada; **la ventana abre a medianoche en Lima, 05:00 UTC en la base** |
| Entrega | Reporte con foto y firma, obligación cumplida y enlazada |
| Evidencia | Dos adjuntos en el bucket privado, bajo el prefijo del tenant, con SHA-256 verificado **contra los bytes que hay en MinIO**, y 16 m de distancia a la sede |
| Gerencia | KPI del día materializados, cumplimiento y puntualidad contados, asistente 5 de 5 |
| Límites | Starter corta en la cuarta plantilla, con el número del plan, sin dejar nada a medias |
| Facturación | Una sede real, cinco facturadas (mínimo Starter), PEN 145.00, pendiente de conciliar; emitir dos veces no duplica |
| Aislamiento | La base central no tiene tabla `users`; la sesión de un cliente no sirve en otro |
| HTTP | Login real con CSRF, 11 pantallas a 200, el dominio central no expone el panel |
| PWA | Manifiesto, service worker, página sin conexión e iconos servidos; el SW no cachea Livewire ni evidencia |
| Privacidad | El bucket de evidencia responde 403 a un anónimo |

## Los dos fallos que aparecieron

### 1. Un reporte entregado no avisaba a nadie

En una empresa con **una sede, una encargada y la dueña** —que es justo con la
que se empieza— la encargada entrega y **no se entera nadie**. Los avisos de
revisión salían solo a las personas asignadas a la sede, y la única asignada era
quien entregó, que no puede revisar lo suyo. La dueña sí podía revisarlo, pero
no estaba asignada a ninguna sede, así que no era candidata.

El reporte se quedaba esperando revisión en silencio, que es exactamente lo que
este producto promete que no pasa.

**Corregido**: si ninguna persona asignada a la sede puede revisar, el aviso cae
a quien administra el parque entero. En una empresa con supervisores asignados
no cambia nada: el respaldo no se usa y nadie recibe avisos de más.

### 2. Un subdominio mal escrito tumbaba al servidor

`acmee.ronda.pe` en vez de `acme.ronda.pe` devolvía **500**, y además el
renderizador de errores tardaba más de lo que PHP permite y dejaba al worker
bloqueado 30 s. Con un worker, una dirección mal escrita bastaba para dejar de
atender a todo el mundo: en la validación, peticiones que tardaron **621 s**.

**Corregido**: ahora es un **404** con una página propia que dice qué hacer. La
prueba que lo fija tarda 0,7 s; sin el arreglo, 274 s.

## Lo que NO se pudo validar aquí

**La PWA en un teléfono de verdad.** Se comprobó el contrato —que los archivos
se sirven, que el service worker no cachea nada privado, que el manifiesto está
en el login— pero no el comportamiento, que es lo que decide la adopción:

- que el navegador ofrezca instalar la aplicación,
- que el borrador vuelva después de cerrar la pestaña,
- que una pantalla ya visitada se sirva sin red,
- que una entrega hecha en modo avión salga sola al recuperar la señal.

Son cuatro pruebas de diez minutos con un móvil de gama media. **Es lo único
que queda entre esto y el piloto**, y no lo puede hacer el código.

## Otras cosas observadas, sin arreglar

- **La hora importa.** La validación se ejecutó a las 23:21 de Lima y la primera
  entrega fue rechazada: la ventana 08:00–20:00 ya había cerrado. El motor hizo
  lo correcto; conviene recordarlo al programar la primera ronda de un cliente.
- **Octane se queda con el código viejo.** Cada cambio de rutas o de `config/`
  necesita `docker compose restart app`. En despliegue hay que dejarlo escrito.
- **Sin correo saliente configurado** no hay avisos reales; en local salen a
  Mailpit (`http://localhost:8025`).

## Cliente de prueba

El guion deja uno creado y borra el de la corrida anterior. El de esta sesión:

- http://piloto8256.localhost:8000
- `duena@piloto8256.test` · `una-contrasena-larga-de-piloto`
