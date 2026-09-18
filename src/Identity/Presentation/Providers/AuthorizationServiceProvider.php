<?php

declare(strict_types=1);

namespace Ronda\Identity\Presentation\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider;
use Illuminate\Support\Facades\Gate;
use Ronda\Directory\Application\Policies\PositionPolicy;
use Ronda\Directory\Application\Policies\SitePolicy;
use Ronda\Directory\Application\Policies\ZonePolicy;
use Ronda\Directory\Domain\Models\Position;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Directory\Domain\Models\Zone;
use Ronda\Evidence\Application\Policies\AttachmentPolicy;
use Ronda\Evidence\Domain\Models\Attachment;
use Ronda\Forms\Application\Policies\TemplatePolicy;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Identity\Application\Policies\OwnerGate;
use Ronda\Identity\Application\Policies\UserPolicy;
use Ronda\Identity\Domain\Models\User;
use Ronda\Insights\Application\Policies\ExportPolicy;
use Ronda\Insights\Application\Policies\ReportPolicy;
use Ronda\Insights\Domain\Models\Export;
use Ronda\Scheduling\Application\Policies\SchedulePolicy;
use Ronda\Scheduling\Domain\Models\Obligation;
use Ronda\Scheduling\Domain\Models\Schedule;
use Ronda\Submissions\Application\Policies\ObligationPolicy;
use Ronda\Submissions\Application\Policies\SubmissionPolicy;
use Ronda\Submissions\Domain\Models\Submission;

/**
 * Enlaza modelos con sus Policies y registra el unico Gate::before del
 * proyecto. RONDA-PLAN-MAESTRO.md sec. 10.3
 *
 * Cada modulo ira anadiendo aqui sus Policies conforme aparezcan sus modelos.
 * Un modelo sin Policy no es un descuido silencioso: Laravel deniega por
 * defecto cuando no encuentra una.
 */
final class AuthorizationServiceProvider extends AuthServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Site::class => SitePolicy::class,
        Zone::class => ZonePolicy::class,
        Position::class => PositionPolicy::class,
        Template::class => TemplatePolicy::class,
        Schedule::class => SchedulePolicy::class,
        Obligation::class => ObligationPolicy::class,
        Submission::class => SubmissionPolicy::class,
        Attachment::class => AttachmentPolicy::class,
        Export::class => ExportPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Los KPI no cuelgan de un modelo: son una lectura del conjunto. La
        // habilidad se define aqui y la decision sigue en una Policy (regla 4).
        Gate::define('view-reports', [ReportPolicy::class, 'view']);

        // El propietario del tenant pasa por encima de toda Policy.
        //
        // spatie/laravel-permission registra su propio Gate::before para
        // resolver permisos por nombre; los dos conviven porque ambos
        // devuelven null cuando no les toca decidir.
        Gate::before(new OwnerGate);
    }
}
