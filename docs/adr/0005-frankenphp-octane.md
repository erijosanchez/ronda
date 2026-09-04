# 0005. FrankenPHP con Octane

**Estado:** aceptado · **Fecha:** 2026-09-03

## Contexto
PHP-FPM + Nginx son dos procesos, dos configuraciones y un arranque completo del framework en cada petición.

## Decisión
FrankenPHP en modo worker mediante Laravel Octane, en un solo contenedor.

## Alternativas descartadas
- **PHP-FPM + Nginx:** convencional y conocido, pero más piezas y más lento.

## Consecuencias que se aceptan
Runtime menos convencional: menos respuestas en internet cuando algo falla, y el código debe ser seguro para estado compartido entre peticiones (nada de estado global mutable en singletons). A cambio, mucho más rendimiento y un contenedor en vez de dos.
