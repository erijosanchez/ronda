# Ronda

Control operativo de sucursales. SaaS multi-tenant, con una base de datos por
cliente.

## Arrancar

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan migrate    # base central
docker compose exec app php artisan db:seed    # crea un cliente de prueba
npm run build                                  # assets
```

`db:seed` deja listo un cliente de demostración con su propia base de datos,
sus roles y un usuario propietario.

## Dónde entrar

La aplicación **no vive en el dominio central**. Cada cliente tiene el suyo, y
es ahí donde se inicia sesión; en el central no existe ni `/login` ni `/panel`.

| | |
|---|---|
| Portada (dominio central) | http://localhost:8000 |
| Aplicación (cliente de prueba) | http://demo.localhost:8000/login |
| Acceso de desarrollo | `owner@demo.test` · `password-de-desarrollo` |

Servicios de apoyo: [Mailpit](http://localhost:8025) ·
[MinIO](http://localhost:9001) · PostgreSQL en `localhost:5433` · Redis en
`localhost:6380`.

## Comandos

```bash
docker compose exec app composer check   # pint + phpstan + rector + deptrac + pest
docker compose exec app vendor/bin/pint  # formatear

docker compose exec app vendor/bin/pest --group=arch      # reglas de arquitectura
docker compose exec app vendor/bin/pest --group=security  # rutas sin auth, CSP
docker compose exec app vendor/bin/pest --group=tenancy   # aislamiento entre clientes
docker compose exec app vendor/bin/pest --group=auth      # autenticacion y permisos
```

La suite tarda un par de minutos: las pruebas de integración provisionan
clientes de verdad, con `CREATE DATABASE`, migración y semilla en cada una. Para
iterar, `--filter`.

> El PHP del host es 8.2 y el proyecto exige 8.4: `php artisan` desde el host
> falla a propósito. Todo pasa por el contenedor.

Tras cambiar rutas, configuración o proveedores hay que reiniciar Octane, que
mantiene la aplicación en memoria:

```bash
docker compose exec app php artisan optimize:clear && docker compose restart app
```

## Documentación

- Decisiones de arquitectura: [`docs/adr/`](docs/adr/)
- Dónde se quedó el trabajo: [`docs/HANDOFF.md`](docs/HANDOFF.md)
- Reglas de código: [`CLAUDE.md`](CLAUDE.md)

El plan maestro de producto y técnico es un **documento interno** y no forma
parte de este repositorio; vive junto a él, en `../RONDA-PLAN-MAESTRO.md`. Las
referencias a «plan §N» que aparecen en el código y en los ADR apuntan a sus
secciones.
