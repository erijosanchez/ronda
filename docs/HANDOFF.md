# Dónde nos quedamos — 14 de septiembre de 2026

Estado del proyecto: **fase 0 cerrada, fase 1 avanzada**.
El plan completo está en `../../RONDA-PLAN-MAESTRO.md`, documento interno que
no forma parte de este repositorio (el repositorio es público).
Léelo antes de retomar; termina con la lista concreta de lo que sigue.

---

## Resumen en una línea

El módulo `Directory` está operativo de punta a punta: se crean sedes, se dan
de alta personas, se les asignan sedes y la frontera por sede se nota de
verdad. No hay ningún bloqueante abierto.

---

## Lo que funciona, verificado ejecutándolo

| Puerta | Resultado | Comando |
|---|---|---|
| Pint (formato) | **PASS**, 121 archivos | `vendor/bin/pint --test` |
| Larastan nivel 8 | **[OK] No errors**, sin baseline | `vendor/bin/phpstan analyse` |
| Rector | **[OK] Rector is done!** | `vendor/bin/rector process --dry-run` |
| Deptrac (módulos) | **0 violaciones** | `vendor/bin/deptrac analyse` |
| Pest | **98 pasan, 1 todo, 0 fallos** | `vendor/bin/pest` |

Todo junto: `docker compose exec app composer check` (sale 0).

**Pantallas que responden 200 con sesión:** `/panel`, `/sedes`, `/sedes/nueva`,
`/sedes/{id}/editar`, `/usuarios`, `/usuarios/nuevo`, `/usuarios/{id}/editar`,
`/usuarios/{id}/sedes`. En el dominio central no existe ninguna.

**La suite tarda ~4 minutos.** La suite `Integration` provisiona tenants reales
(`CREATE DATABASE` + migrar + sembrar en cada prueba). Para iterar, `--filter`.

---

## Lo que se hizo esta sesión

### Sedes: alta y edición

Actions `CreateSite` y `UpdateSite` con `SiteData`. `UpdateSite` recibe la sede
ya resuelta y no un id: buscarla dentro se saltaría la Policy y el scope de
frontera que la autorizaron.

La unicidad del código consulta la tabla y no el modelo, así que cuenta también
las sedes con borrado lógico. Un código de sede no se recicla.

### Usuarios: gestión completa y los invariantes de `Gate::before`

A la tabla `users` le faltaban tres columnas del plan §8.3: teléfono cifrado,
estado y borrado lógico. Añadidas en migración aparte, porque el parque ya
tiene bases creadas y una migración aplicada no vuelve a ejecutarse.

**Cerrado el punto abierto que arrastrábamos.** `Gate::before` concede todo al
propietario antes de que ninguna Policy se ejecute, así que los dos invariantes
viven en `DeleteUser`: nadie se borra a sí mismo, y el cliente no puede
quedarse sin propietario. Un test lo deja explícito: comprueba que
`$owner->can('delete', $owner)` devuelve `true` —la Policy no llega a mirarse—
y que la Action lanza igualmente.

La comprobación y el borrado van juntos en transacción aunque solo se escriba
una tabla: sin ella, dos bajas simultáneas de los dos últimos propietarios
contarían ambas «queda otro». El bloqueo selecciona filas en vez de contarlas
porque **PostgreSQL rechaza `FOR UPDATE` junto a una función de agregación**.

### Asignación persona ↔ sede

Es la pantalla que da sentido a la frontera: hasta que alguien asigna, un
encargado no ve ninguna sede. Hay un test que recorre el ciclo entero.

El listado pagina (regla 5) pero **lo marcado no vive en la página**, vive en el
componente indexado por sede. Sin eso, asignar un parque de trescientas sedes
sería imposible.

`user_site` tiene modelo propio (`SiteAssignment`) porque no es una tabla de
unión vacía: lleva el cargo y el rol de esa persona en esa sede.

### Dos premisas mías que eran falsas

Las anoto porque el patrón se repite y conviene desconfiar:

1. Escribí que «quien no administra sedes solo verá las suyas en el selector».
   Cierto como mecanismo, pero **con los roles predefinidos no llega a notarse**:
   los dos que traen `user.manage` (owner y admin) traen también `site.manage`.
   El test lo prueba con permisos a medida, que es el caso que el plan
   contempla: el cliente clona y ajusta sus roles.
2. Dos tests creaban cargos que la provisión ya siembra y chocaban con la
   restricción de unicidad.

---

## Puntos abiertos (ninguno bloquea)

### Zonas y cargos no tienen pantalla

**Es el hueco más visible.** El formulario de sede ofrece un selector de zonas,
pero no hay forma de crear una zona desde la interfaz: hoy solo se pueden crear
por consola o semilla. Lo mismo con los cargos, que llegan del catálogo
sembrado y no se pueden ajustar. Tampoco tienen Policy propia; solo `Site` la
tiene.

### La regla «los DTOs son inmutables» choca con spatie/laravel-data

PHP no permite que una clase `readonly` extienda una que no lo es, y
`Spatie\LaravelData\Data` no lo es. Pest exige el modificador a nivel de clase,
así que ningún DTO de spatie puede pasar la regla. Los cuatro DTOs del proyecto
son clases `readonly` propias. Hay que decidir cuál manda antes de que un DTO
tenga que atarse a un `Request`.

### No se registra el último acceso

La columna `last_login_at` existe desde la fase 0 y **nadie la escribe**. Hace
falta un listener del evento `Login` de Fortify.

### Búsqueda por teléfono imposible

Va cifrado (§8.6), así que no se puede comparar ni indexar en SQL. El buscador
de usuarios solo mira nombre y correo. Es el precio aceptado del cifrado, pero
conviene tenerlo presente si alguien lo pide.

---

## Lo que sigue, en orden

1. **Zonas y cargos en la interfaz.** Cierra el hueco de arriba y completa el
   módulo `Directory`. Zonas tiene jerarquía, así que el formulario necesita un
   selector de zona padre que evite ciclos.
2. **Registrar el último acceso** con un listener del evento `Login`.
3. **Provisión asíncrona con pantalla de progreso** (plan §7.2). Hoy el job va
   a la cola pero nadie mira su estado; el objetivo del plan es menos de 30 s
   con progreso visible.
4. **Sentry y Laravel Pulse** (plan §11.5). Solo hay logs.
5. **`lang/en` solo tiene `roles`, `permissions` y `user-status`**; le falta
   `validation`.
6. **Módulo `Forms`** (plan §9.2): el diseñador de plantillas. Es el siguiente
   cimiento grande, y ya hay sedes a las que colgarlo.

---

## Cómo retomar

```bash
cd c:/proyecto/ronda
docker compose up -d
docker compose ps                              # los ocho deben salir healthy
docker compose exec app php artisan migrate    # base central
docker compose exec app php artisan db:seed    # crea el tenant de demostración
npm run build                                  # assets (Flux va en el bundle)
docker compose exec app composer check         # las cinco puertas
```

- Portada (central): http://localhost:8000 — aquí **no** hay login, da 404.
- Aplicación (tenant): http://demo.localhost:8000/login
  - Usuario: `owner@demo.test` · Contraseña: `password-de-desarrollo`
- Mailpit: http://localhost:8025 · MinIO: http://localhost:9001
- PostgreSQL desde el host: `localhost:5433` · Redis: `localhost:6380`

Tras cambiar rutas, configuración o proveedores hay que reiniciar Octane, que
cachea la aplicación en memoria:

```bash
docker compose exec app php artisan optimize:clear
docker compose restart app
```

Tras cambiar migraciones de tenant, aplicarlas al parque:

```bash
docker compose exec app php artisan tenants:migrate
docker compose exec app php artisan tenants:seed
```

---

## Una costumbre que vale la pena mantener

Han aparecido **dos pruebas de seguridad que pasaban sin comprobar nada**:
`toContain()` es variádico en Pest y se tragó un mensaje como valor a buscar; y
un recorrido de rutas filtraba por un middleware que solo casaba con Horizon,
de modo que nunca pidió una pantalla de la aplicación.

Las dos se encontraron imprimiendo lo que la prueba recorría de verdad. **Una
prueba de seguridad que pasa a la primera conviene sondearla antes de darla por
buena**, o romper a propósito lo que debería detectar y ver que falla.

Las reglas de código están en [`../CLAUDE.md`](../CLAUDE.md). Las decisiones,
en [`adr/`](adr/).
