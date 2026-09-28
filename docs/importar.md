# Importar sedes y personas desde un CSV

Para que un cliente con ochenta locales no tenga que darlos de alta uno a uno.
Está en **Sedes → Importar** y en **Personas → Importar**; hace falta el mismo
permiso que para crearlas a mano.

## Cómo funciona

Dos pasos, siempre:

1. **Analizar.** Lee el archivo entero, lo compara con lo que ya hay y dice
   cuántas filas son altas, cuántas actualizaciones y qué está mal, con el
   número de fila de la hoja. **No escribe nada.**
2. **Confirmar.** Escribe todo o nada, en una sola transacción. El botón no
   aparece mientras quede una fila con problemas.

Entre un paso y otro se vuelve a leer el archivo y a comparar contra la base:
si alguien creó una sede con ese código mientras tanto, se ve antes de escribir.

Tope: **2000 filas por archivo**. Con más, hay que partirlo.

## El archivo

Se acepta lo que exporta cualquier hoja de cálculo:

- Separador `;` o `,` (Excel en español guarda con `;`).
- UTF-8 o Windows-1252 (lo que produce «Guardar como CSV» en Windows).
- Con o sin BOM.
- Cabeceras en cualquier combinación de mayúsculas y acentos: `Código`,
  `CODIGO` y `codigo` son la misma columna.

Cada pantalla ofrece **Descargar plantilla**, que trae las cabeceras y una fila
de ejemplo.

### Sedes

| Columna        | Obligatoria | Notas                                     |
| -------------- | ----------- | ----------------------------------------- |
| `codigo`       | Sí          | Identifica la sede. Si ya existe, se actualiza |
| `nombre`       | Sí          |                                           |
| `zona`         | No          | Por su nombre; la zona tiene que existir  |
| `direccion`    | No          |                                           |
| `latitud`      | No          | Acepta coma o punto decimal               |
| `longitud`     | No          |                                           |
| `zona_horaria` | No          | Por defecto `America/Lima`                |
| `abre`         | No          | `HH:MM`; `8:30` también vale              |
| `cierra`       | No          |                                           |

### Personas

| Columna    | Obligatoria | Notas                                             |
| ---------- | ----------- | ------------------------------------------------- |
| `nombre`   | Sí          |                                                   |
| `correo`   | Sí          | Identifica a la persona. Si ya existe, se actualiza |
| `telefono` | No          |                                                   |
| `roles`    | No          | Varios separados por `\|`. Por nombre («Encargado») o clave (`site_manager`) |
| `estado`   | No          | `activo` o `suspendido`. Por defecto activo        |
| `sedes`    | No          | Códigos de sede separados por `\|`; tienen que existir |

## Lo que el importador no hace, a propósito

- **No reparte contraseñas.** Quien entra nuevo recibe una aleatoria que no ve
  nadie y accede con «olvidé mi contraseña», que le manda un enlace por correo.
  Una columna `contrasena` sería repartir las claves de todo el personal en una
  hoja que acabará reenviada por correo; si el archivo la trae, se ignora.
- **No le toca la contraseña a quien ya existe.** Importar corrige datos; no
  echa a nadie de su sesión.
- **No importa al propietario ni concede su rol.** `UserPolicy::update` solo
  deja que el propietario se edite a sí mismo, para que un administrador no
  pueda cambiarle el correo y quedarse con la cuenta. Un CSV no puede ser la
  puerta de atrás de esa regla.
- **No crea zonas ni sedes por su cuenta.** Si una fila apunta a algo que no
  existe, lo dice y no lo inventa.
- **No borra.** Una celda `sedes` vacía deja sus sedes como están; retirar una
  asignación se hace desde la ficha, a conciencia.
- **No recicla códigos ni correos de lo dado de baja**, que siguen ocupando el
  índice único de la base.

## Si algo falla

El informe da el número de fila **tal como se ve en la hoja de cálculo**,
contando la cabecera como fila 1. Se corrige el archivo y se vuelve a subir:
como lo que ya existe se actualiza en vez de duplicarse, reintentar con el
archivo entero es seguro.
