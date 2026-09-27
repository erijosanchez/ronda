# Webhooks salientes

Ronda llama al sistema del cliente cuando pasa algo, en vez de obligarle a
preguntar cada minuto. Se configuran en **Integraciones → Webhooks** dentro de
la aplicación del cliente (hace falta el permiso `manage-api`).

Este documento es la referencia de lo que sale de Ronda. Para la API de
consulta, ver `/docs/api`.

## Eventos

| Evento                 | Cuándo                                            |
| ---------------------- | ------------------------------------------------- |
| `submission.submitted` | Alguien entrega un reporte                        |
| `submission.approved`  | Se aprueba un reporte                             |
| `submission.rejected`  | Se devuelve un reporte                            |
| `obligation.missed`    | Cierra la ventana de un reporte y no se entregó   |

El nombre del evento es contrato público: no se renombra sin una versión nueva.

## Forma del aviso

`POST` con `Content-Type: application/json`:

```json
{
  "event": "submission.submitted",
  "sent_at": "2026-09-26T14:03:11+00:00",
  "data": {
    "id": 4821,
    "state": "submitted",
    "site_id": 12,
    "template_id": 3,
    "template_version_id": 7,
    "obligation_id": 90120,
    "submitted_at": "2026-09-26T14:03:10+00:00",
    "is_late": false
  }
}
```

`data` trae las mismas claves que devuelve la API para ese recurso. Dos formas
distintas de decir lo mismo obligarían a escribir dos lectores.

Cabeceras:

| Cabecera             | Contenido                                     |
| -------------------- | --------------------------------------------- |
| `X-Ronda-Event`      | Nombre del evento                             |
| `X-Ronda-Delivery`   | Identificador del intento de entrega          |
| `X-Ronda-Timestamp`  | Marca de tiempo Unix usada en la firma        |
| `X-Ronda-Signature`  | HMAC-SHA256 en hexadecimal                    |

## Verificar la firma

La firma es `HMAC-SHA256` de la cadena `«marca.cuerpo»` con el secreto que
Ronda muestra **una sola vez** al crear el destino. La marca va dentro de lo
firmado para que un aviso capturado no se pueda reenviar días después.

```php
$marca = $request->header('X-Ronda-Timestamp');
$firma = $request->header('X-Ronda-Signature');

$esperada = hash_hmac('sha256', $marca.'.'.$request->getContent(), $secreto);

if (! hash_equals($esperada, (string) $firma)) {
    abort(401);
}

// Y rechazar lo que llegue con mucho retraso: sin esto, quien capture un
// aviso valido puede reenviarlo cuando quiera.
if (abs(time() - (int) $marca) > 300) {
    abort(401);
}
```

En Node:

```js
const esperada = crypto
  .createHmac('sha256', secreto)
  .update(`${marca}.${cuerpoCrudo}`)
  .digest('hex');
```

El cuerpo tiene que ser el **crudo**, tal como llegó: volver a serializar el
JSON cambia los espacios y la firma deja de cuadrar.

## Reintentos

Se considera entregado con cualquier respuesta 2xx. Cualquier otra cosa —un
error, un tiempo de espera agotado, **o una redirección**— cuenta como fallo.

Hay seis intentos, esperando 1, 2, 4, 8 y 16 minutos entre ellos. Tras 15
fallos seguidos, el destino se apaga solo y hay que volver a encenderlo desde
la pantalla; seguir llamando cada minuto a una URL muerta es maltratar un
servidor ajeno.

El registro de entregas de la pantalla dice qué se mandó, cuándo, cuántas veces
se intentó y qué respondió el otro lado, y permite **reenviar a mano** una
entrega con el mismo cuerpo que se guardó.

Conviene responder rápido y hacer el trabajo después: el tiempo de espera es de
10 segundos.

## Qué direcciones se aceptan

Solo `https`, y solo direcciones alcanzables desde internet. Ronda rechaza
nombres sin punto (`postgres`), direcciones de bucle local, redes privadas y
enlace local (incluido `169.254.169.254`), tanto escritas directamente como
escondidas detrás de un nombre que resuelve ahí. Tampoco se siguen
redirecciones, y la dirección se vuelve a comprobar en el momento de llamar:
un nombre válido hoy puede apuntar mañana a una dirección interna.

En desarrollo, `WEBHOOKS_ALLOW_INSECURE=true` levanta esas restricciones. En un
servidor de verdad sería la puerta abierta al SSRF que el resto del código se
dedica a cerrar.

## Que no se repita el trabajo

Un mismo evento puede llegar dos veces: si el cliente responde tarde y Ronda
da el intento por perdido, lo reintenta. `X-Ronda-Delivery` identifica el
intento, y `data.id` el recurso: quien integra debería ignorar lo que ya
procesó.
