<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La organizacion del cliente: zonas, sedes, puestos y quien trabaja donde.
 * RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * Vive en la base del TENANT. Reglas de datos (sec. 8.6) aplicadas aqui:
 * borrado logico en todo lo que el usuario puede eliminar, marcas de tiempo en
 * UTC, y claves foraneas con ON DELETE RESTRICT por defecto, que en Laravel es
 * lo que hace `restrictOnDelete()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table): void {
            $table->id();
            $table->string('name');

            // Jerarquia: una zona puede colgar de otra (pais > region > distrito).
            // Se borra restringido: vaciar una zona padre no debe llevarse por
            // delante a sus hijas en silencio.
            $table->foreignId('parent_id')->nullable()->constrained('zones')->restrictOnDelete();

            // Responsable de la zona. Nullable porque una zona puede existir
            // antes de que se decida quien la lleva.
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('parent_id');
        });

        Schema::create('positions', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();

            // Nivel jerarquico del cargo. Sirve para ordenar y para reglas de
            // aprobacion por nivel (sec. 9.4), no para autorizar: eso son
            // permisos.
            $table->unsignedSmallInteger('level')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sites', function (Blueprint $table): void {
            $table->id();

            // Codigo interno del cliente (el que usan en sus propios sistemas).
            $table->string('code')->unique();
            $table->string('name');

            $table->foreignId('zone_id')->nullable()->constrained('zones')->restrictOnDelete();

            $table->string('address')->nullable();

            // Coordenadas en decimal, no en coma flotante: la precision importa
            // para el geoetiquetado de la evidencia (sec. 9.5).
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 11, 7)->nullable();

            // Cada sede tiene su zona horaria. Un local en Iquitos y otro en
            // Lima pueden tener ventanas distintas (sec. 8.6).
            $table->string('timezone')->default('America/Lima');

            // Ventana horaria de operacion. Se guarda como hora local de la
            // sede, no en UTC: una sede que abre a las 08:00 lo hace a las 08:00
            // de su reloj, cambie o no el desfase.
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();

            // Vigencia. Una sede cerrada no se borra: conserva su historial.
            $table->date('active_from')->nullable();
            $table->date('active_until')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('zone_id');
            // El listado filtra por vigencia constantemente.
            $table->index(['active_until', 'active_from']);
        });

        // El plan (sec. 8.3) la llama `user_site`, no `site_user`, que es lo que
        // Laravel deduciria por orden alfabetico. Se respeta el nombre del plan
        // y se declara la tabla a mano en la relacion del modelo.
        Schema::create('user_site', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();

            // Cargo que ocupa esa persona en esa sede.
            $table->foreignId('position_id')->nullable()->constrained('positions')->restrictOnDelete();

            // Rol en esa sede concreta. La misma persona puede ser encargada en
            // una y supervisora en otra.
            $table->string('role')->nullable();

            $table->timestamps();

            // Una persona no se asigna dos veces a la misma sede.
            $table->unique(['user_id', 'site_id']);
            // La frontera por sede consulta por usuario en cada peticion.
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_site');
        Schema::dropIfExists('sites');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('zones');
    }
};
