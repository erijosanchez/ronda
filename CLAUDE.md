# Ronda — instrucciones del proyecto

SaaS multi-tenant de control operativo de sucursales. El plan completo vive en
`../RONDA-PLAN-MAESTRO.md`. Este archivo es el resumen operativo: lo que hay
que respetar al escribir codigo aqui.

## Contexto de ejecucion

**Todo corre en Docker.** El PHP local es 8.2 y el proyecto exige 8.4, asi que
`php artisan` desde el host falla a proposito (`platform_check.php`). Usa
siempre el contenedor:

```bash
docker compose up -d
docker compose exec app php artisan <comando>
docker compose exec app vendor/bin/pest
```

Composer resuelve contra la plataforma destino declarada en
`composer.json > config.platform` (PHP 8.4 + extensiones del Dockerfile), por eso
`composer require` desde el host funciona con `--no-scripts`.

## Reglas no negociables

Cada una tiene un test que la verifica. Si rompes una, la CI para el merge.
No las relajes: la deuda de `reports-trimax` nacio exactamente de tenerlas
escritas y no verificadas.

1. **Un controlador o componente Livewire no contiene logica de negocio.**
   Valida, invoca una Action, devuelve.
2. **Toda escritura de negocio pasa por una Action** invocable de proposito
   unico en `src/<Modulo>/Application/Actions/`.
3. **Toda escritura multi-tabla va dentro de `DB::transaction`.**
4. **Ninguna comprobacion de rol fuera de una Policy.** Nada de `hasRole()` ni
   `isAdmin()` en controladores, componentes o servicios.
5. **Todo listado pagina.** Sin `paginate()` o `cursorPaginate()` no se
   aprueba.
6. **`declare(strict_types=1)` en todos los archivos.**
7. **Clases `final` por defecto.**
8. **El dominio no usa facades, ni `Request`, ni Eloquent.** Deptrac lo
   verifica.
9. **Sin `mixed` en firmas publicas.** Larastan nivel 8, sin baseline.
10. **Textos de interfaz siempre por `__()`.** Nunca literales en Blade.

Ademas:

- **Ninguna ruta sin autenticacion.** El test `RouteProtectionTest` recorre la
  tabla de rutas y falla si alguna carece de middleware de auth. Para hacerla
  publica hay que agregarla a la lista blanca del test, y eso pasa por revision.
- **Ningun adjunto en `public/`.** Disco S3 privado con URL firmada de 5
  minutos, y la Policy se evalua antes de firmar.
- **Prohibido `{!! !!}` en Blade.**
- **Prohibido `DB::raw()` con entrada de usuario.**

## Estructura

```
src/<Modulo>/
├── Domain/          Modelos, estados, value objects, eventos, excepciones
├── Application/     Actions, Data (DTOs), Queries, Jobs, Policies
├── Infrastructure/  Repositorios, listeners, servicios externos
├── Presentation/    Livewire, vistas, rutas
├── Database/        Migraciones, factories, seeders
└── Tests/
```

Modulos: `Platform`, `Identity`, `Directory`, `Forms`, `Workflow`,
`Scheduling`, `Submissions`, `Evidence`, `Notifications`, `Insights`, `Api`.

La regla de dependencia apunta hacia adentro:
`Presentation -> Application -> Domain`. `Infrastructure` implementa, no dirige.

## Convenciones

- **Codigo en ingles, interfaz en espanol.** Nunca mezclar en el mismo
  identificador.
- Action: verbo + sustantivo (`SubmitReport`). DTO: `<Nombre>Data`.
  Query: `<Nombre>Query`. Evento: sustantivo + verbo en pasado
  (`SubmissionApproved`). Job: `<Verbo>Job`.
- Tablas `snake_case` plural en ingles. Rutas `kebab-case` en espanol.
- Commits: Conventional Commits.
- Dinero en `numeric(14,2)` con moneda aparte. Nunca `float`.
- Marcas de tiempo en UTC en la base; se convierten a la zona de la sede al
  mostrar.

## Antes de dar algo por terminado

```bash
docker compose exec app composer check   # pint + phpstan + deptrac + pest
```

Y la checklist completa del PR (`.github/pull_request_template.md`).

## Decisiones ya tomadas

No las reabras sin un ADR nuevo. Estan en `docs/adr/`:
base de datos por tenant, PostgreSQL, Livewire + Flux, FrankenPHP + Octane,
Actions + DTOs, maquina de estados declarativa, obligaciones materializadas,
evidencia privada con URL firmada, PWA antes que app nativa.
