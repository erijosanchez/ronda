# 0010. PWA antes que app nativa

**Estado:** aceptado · **Fecha:** 2026-09-03

## Contexto
El usuario diario es un encargado de local con un teléfono de gama media y señal irregular. Una app nativa exige un equipo móvil, dos tiendas y un ciclo de publicación propio.

## Decisión
La v1 es una PWA instalable con captura offline en IndexedDB, cámara y geolocalización por API del navegador.

## Alternativas descartadas
- **Flutter o React Native desde la v1:** coste de un equipo adicional antes de tener un solo cliente pagando.

## Consecuencias que se aceptan
Notificaciones push limitadas en iOS (exigen que la PWA esté instalada). Se acepta y se compensa con WhatsApp, que es donde ya vive la operación. La app nativa se decide con datos de uso, no por anticipado.
