# 0012. Plantillas versionadas y modelo híbrido JSONB

**Estado:** aceptado · **Fecha:** 2026-09-14

## Contexto

Ronda es un motor de formularios dinámicos: cada cliente define qué se pide en
cada sede. Eso plantea dos problemas de modelado distintos que conviene no
confundir.

**Cómo se guarda la definición del formulario.** Un formulario tiene campos, y
cada campo tiene clave, tipo, etiqueta, reglas, visibilidad condicional y la
marca *reportable* (plan §9.2). Publicar cambios no puede alterar lo ya
entregado: un arqueo aprobado en marzo tiene que seguir leyéndose con la
plantilla de marzo, no con la de hoy.

**Cómo se guardan las respuestas.** Un motor dinámico tiene dos malas salidas
clásicas: EAV puro, con consultas imposibles, o una tabla por plantilla, con
migraciones infinitas.

## Decisión

### La definición: JSONB inmutable por versión

`templates` guarda la identidad estable (código, nombre, estado) y
`template_versions` guarda el esquema completo de esa versión como **un
documento JSONB**.

Una versión publicada **no se modifica nunca**. Editar crea un borrador nuevo;
publicarlo incrementa el número de versión y deja intactas las anteriores. Un
envío apunta a la versión concreta con la que se respondió.

No se normalizan los campos en una tabla `template_fields` porque una versión
es un documento inmutable que siempre se lee entero: partirlo en filas obliga a
recomponerlo en cada lectura y abre la puerta a que un campo se edite sin pasar
por la publicación, que es justo lo que la inmutabilidad debe impedir.

### Las respuestas: híbrido JSONB + valores normalizados (plan §8.4)

- **`submissions.data` JSONB** guarda la respuesta completa y fiel, con índice
  GIN. Es la fuente de verdad y sobrevive a cualquier cambio de plantilla.
- **`submission_values`** replica únicamente los campos que el diseñador marcó
  como *reportables* (el monto del arqueo, el número de voucher, el puntaje),
  en columnas tipadas e indexadas. Es lo que alimenta filtros, KPI y
  exportaciones sin tocar el JSONB.

La replicación la hace la misma Action que guarda el envío, dentro de la
transacción. Un job nocturno de reconciliación verifica que ambos lados
coinciden.

## Alternativas descartadas

- **EAV puro** (una fila por respuesta de campo): cada consulta de pantalla se
  convierte en una pila de *joins* y los KPI dejan de ser viables.
- **Una tabla por plantilla**: migraciones cada vez que un cliente añade un
  campo, en un producto donde eso es la operación normal.
- **Solo JSONB, sin valores normalizados**: los KPI y los filtros dependerían
  de expresiones sobre JSON en cada consulta, y el índice GIN no sostiene bien
  los rangos numéricos ni los ordenamientos.
- **Versionar con `updated_at` y auditoría**: no basta. Sin versiones
  explícitas no hay forma de renderizar un envío antiguo con la forma que tenía
  el formulario entonces.

## Consecuencias que se aceptan

- **Dos representaciones de la misma respuesta que pueden divergir.** Es el
  coste real de esta decisión. Se mitiga replicando dentro de la misma
  transacción y reconciliando de noche; sin esas dos cosas, el híbrido es una
  trampa.
- Cambiar un campo de *reportable* a no reportable, o al revés, exige repoblar
  `submission_values` de los envíos ya existentes.
- El esquema JSONB no lo valida la base de datos. La integridad de la
  definición la sostienen los value objects del dominio y sus pruebas, no una
  restricción de PostgreSQL.
- Las versiones se acumulan. Se asume: son documentos pequeños y son el único
  modo de que un envío antiguo siga siendo legible.
- **El estado de una plantilla es un enum, no una máquina de estados.** El ADR
  0007 aplica al flujo de los envíos, que tiene aprobadores, escalamiento y
  transiciones con guardas. Una plantilla solo va de borrador a publicada y de
  ahí a archivada; montar el paquete de estados para eso sería ceremonia sin
  invariante que proteger.
