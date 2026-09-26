<?php

declare(strict_types=1);

namespace Ronda\Platform\Presentation\Http;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Ronda\Platform\Application\Queries\PublicPlansQuery;
use Ronda\Platform\Domain\PlanFeature;

/**
 * El sitio publico. RONDA-PLAN-MAESTRO.md sec. 15.3 y 3.6
 *
 * Dos paginas y ninguna logica: valida nada, invoca una consulta y devuelve
 * (regla 1). Es un controlador y no un componente Livewire porque aqui no hay
 * nada que reaccione: es contenido, y cuanto menos JavaScript cargue quien
 * llega por primera vez, mejor.
 *
 * Los precios vienen de la base (ver PublicPlansQuery): el numero que se
 * publica y el que se cobra tienen que ser el mismo.
 */
final class PublicSiteController extends Controller
{
    public function home(PublicPlansQuery $plans): View
    {
        return view('platform::public.home', [
            'plans' => $plans(),
            'features' => PlanFeature::cases(),
            'trialDays' => (int) config('billing.trial_days', 14),
        ]);
    }

    public function pricing(PublicPlansQuery $plans): View
    {
        return view('platform::public.pricing', [
            'plans' => $plans(),
            'features' => PlanFeature::cases(),
            'trialDays' => (int) config('billing.trial_days', 14),
            'freeMonths' => (int) config('billing.yearly_free_months', 2),
        ]);
    }
}
