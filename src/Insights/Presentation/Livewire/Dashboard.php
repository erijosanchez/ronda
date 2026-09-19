<?php

declare(strict_types=1);

namespace Ronda\Insights\Presentation\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Identity\Domain\RoleName;
use Ronda\Insights\Application\Data\KpiFilter;
use Ronda\Insights\Application\Queries\KpiSummaryQuery;
use Ronda\Insights\Application\Queries\RepeatOffendersQuery;
use Ronda\Insights\Application\Queries\SiteRankingQuery;
use Ronda\Insights\Domain\ValueObjects\KpiSummary;
use Ronda\Platform\Application\Queries\OnboardingProgressQuery;
use Ronda\Platform\Domain\ValueObjects\OnboardingProgress;

/**
 * El panel: donde se aterriza y donde se mide.
 * RONDA-PLAN-MAESTRO.md sec. 9.6 y 13
 *
 * Lee SOLO de `kpi_daily`, que materializa el job: ninguna pantalla agrega
 * sobre la tabla de envios en tiempo real.
 *
 * Quien no puede ver reportes (un encargado de local) sigue viendo su
 * bienvenida y sus accesos: el panel no es un muro.
 */
final class Dashboard extends Component
{
    use WithPagination;

    /** Periodos que se ofrecen, en dias. */
    private const array PERIODS = [7, 30, 90];

    /** A partir de cuantos incumplimientos de la misma plantilla hay reincidencia. */
    private const int REPEAT_THRESHOLD = 3;

    #[Url(except: 30)]
    public int $days = 30;

    #[Url(except: '')]
    public string $siteId = '';

    #[Url(except: '')]
    public string $templateId = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['days', 'siteId', 'templateId'], true)) {
            $this->resetPage('ranking');
        }
    }

    public function render(): View
    {
        $puedeVer = auth()->user()?->can('view-reports') ?? false;

        if (! $puedeVer) {
            return view('insights::dashboard', [
                'showKpis' => false,
                'userName' => (string) auth()->user()?->name,
                'tenantName' => (string) tenant('name'),
                'roles' => $this->roleLabels(),
                'onboarding' => $this->onboarding(),
            ]);
        }

        $filtro = $this->filter();

        return view('insights::dashboard', [
            'showKpis' => true,
            'userName' => (string) auth()->user()?->name,
            'tenantName' => (string) tenant('name'),
            'roles' => $this->roleLabels(),
            'onboarding' => $this->onboarding(),
            'periods' => self::PERIODS,
            'summary' => resolve(KpiSummaryQuery::class)($filtro),
            'ranking' => resolve(SiteRankingQuery::class)($filtro),
            'repeatOffenders' => resolve(RepeatOffendersQuery::class)($filtro, self::REPEAT_THRESHOLD),
            'repeatThreshold' => self::REPEAT_THRESHOLD,
            'sites' => Site::query()->orderBy('name')->get(['id', 'name']),
            'templates' => Template::query()->orderBy('name')->get(['id', 'name']),
            'from' => $filtro->from,
            'to' => $filtro->to,
            'emptySummary' => new KpiSummary,
        ]);
    }

    /**
     * Lo que le falta a la cuenta para estar en marcha, o nada si ya lo esta.
     *
     * El panel es donde se aterriza, asi que es donde tiene que estar el aviso
     * mientras la cuenta esta a medio montar: un asistente al que solo se llega
     * escribiendo la URL no lo ve nadie.
     */
    private function onboarding(): ?OnboardingProgress
    {
        if (! (auth()->user()?->can('complete-onboarding') ?? false)) {
            return null;
        }

        $avance = resolve(OnboardingProgressQuery::class)();

        return $avance->finished() ? null : $avance;
    }

    private function filter(): KpiFilter
    {
        $dias = in_array($this->days, self::PERIODS, true) ? $this->days : 30;
        $hoy = CarbonImmutable::now('UTC');

        return new KpiFilter(
            // El periodo termina hoy: el dia en curso cuenta con lo que ya se
            // sabe de el, y el job lo recalcula cada hora.
            from: $hoy->subDays($dias - 1)->toDateString(),
            to: $hoy->toDateString(),
            siteId: $this->siteId === '' ? null : (int) $this->siteId,
            templateId: $this->templateId === '' ? null : (int) $this->templateId,
        );
    }

    /**
     * Nombres legibles de los roles del usuario.
     *
     * Leer los roles para mostrarlos no es comprobar permisos: la autorizacion
     * sigue viviendo solo en las Policies.
     *
     * @return list<string>
     */
    private function roleLabels(): array
    {
        $names = auth()->user()?->roles->pluck('name')->all() ?? [];

        return array_values(array_map(
            static fn (string $name): string => RoleName::tryFrom($name)?->label() ?? $name,
            $names,
        ));
    }
}
