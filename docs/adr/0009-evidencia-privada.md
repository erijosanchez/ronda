# 0009. Evidencia en almacenamiento privado con URL firmada

**Estado:** aceptado · **Fecha:** 2026-09-03

## Contexto
En reports-trimax los adjuntos financieros se guardaron en un disco público, y el servidor web los sirvió sin autenticación. Fue el hallazgo crítico de su auditoría.

## Decisión
Ningún adjunto en `public/`. Disco S3 privado (MinIO en local, Cloudflare R2 en producción), URL firmada de 5 minutos, y la Policy del recurso se evalúa antes de firmar. Cada archivo guarda su SHA-256.

## Alternativas descartadas
- **Disco público:** la clase de fallo que ya ocurrió.
- **Blobs en la base:** infla los respaldos y no escala.

## Consecuencias que se aceptan
Cada acceso a evidencia cuesta una firma y una consulta de autorización. Es exactamente el coste que se quería pagar.
