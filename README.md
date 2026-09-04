# Ronda

Control operativo de sucursales. SaaS multi-tenant.

## Arrancar

```bash
cp .env.example .env          # ya está hecho en local
docker compose up -d --build
docker compose exec app php artisan migrate
```

- App: http://localhost:8000
- Correo (Mailpit): http://localhost:8025
- MinIO: http://localhost:9001
- PostgreSQL desde el host: `localhost:5433`
- Redis desde el host: `localhost:6380`

## Comandos

```bash
docker compose exec app composer check   # pint + phpstan + deptrac + pest
docker compose exec app vendor/bin/pest --group=arch      # reglas de arquitectura
docker compose exec app vendor/bin/pest --group=security  # rutas sin auth
docker compose exec app vendor/bin/pest --group=tenancy   # aislamiento de tenant
docker compose exec app vendor/bin/pint                   # formatear
```

> El PHP del host es 8.2 y el proyecto exige 8.4: `php artisan` desde el host
> falla a propósito. Todo pasa por el contenedor.

## Documentación

- Plan maestro: [`../RONDA-PLAN-MAESTRO.md`](../RONDA-PLAN-MAESTRO.md)
- Decisiones de arquitectura: [`docs/adr/`](docs/adr/)
- Reglas de código: [`CLAUDE.md`](CLAUDE.md)
