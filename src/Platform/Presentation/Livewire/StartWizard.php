<?php

declare(strict_types=1);

namespace Ronda\Platform\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Ronda\Platform\Application\Queries\OnboardingProgressQuery;
use Ronda\Platform\Domain\ValueObjects\OnboardingStep;

/**
 * El asistente de arranque: «tu cuenta esta lista, ahora esto».
 * RONDA-PLAN-MAESTRO.md sec. 15.3
 *
 * No hace ninguno de los pasos: lleva a la pantalla que ya los hace. Un
 * asistente que duplica los formularios acaba siendo un segundo sitio donde
 * arreglar cada cosa, y el dia que uno cambia el otro miente.
 *
 * Lo que aporta es orden y estado: cual toca ahora y cuanto falta. Se recalcula
 * en cada visita desde los datos (ver OnboardingProgressQuery), asi que volver
 * aqui despues de crear una sede muestra la verdad sin que nadie marque nada.
 */
final class StartWizard extends Component
{
    public function mount(): void
    {
        $this->authorize('complete-onboarding');
    }

    public function render(OnboardingProgressQuery $progress): View
    {
        $avance = $progress();
        $siguiente = $avance->next();

        $pasos = [];

        foreach (OnboardingStep::cases() as $paso) {
            $pasos[] = [
                'step' => $paso,
                'done' => $avance->isDone($paso),
                'current' => $paso === $siguiente,
                'route' => route($this->routeFor($paso)),
                'title' => $this->titleFor($paso),
                'description' => $this->descriptionFor($paso),
                'cta' => $this->callToActionFor($paso),
            ];
        }

        return view('platform::start-wizard', [
            'pasos' => $pasos,
            'avance' => $avance,
            'siguiente' => $siguiente,
        ]);
    }

    /**
     * A donde lleva cada paso. Vive aqui y no en el enum porque las rutas son
     * cosa de la interfaz: el dominio no sabe que existe una URL.
     */
    private function routeFor(OnboardingStep $step): string
    {
        return match ($step) {
            OnboardingStep::Templates => 'templates.catalog',
            OnboardingStep::Sites => 'sites.create',
            OnboardingStep::Team => 'users.create',
            OnboardingStep::Schedules => 'schedules.create',
            OnboardingStep::FirstReport => 'submissions.pending',
        };
    }

    private function titleFor(OnboardingStep $step): string
    {
        return match ($step) {
            OnboardingStep::Templates => __('Choose what gets checked'),
            OnboardingStep::Sites => __('Add your branches'),
            OnboardingStep::Team => __('Invite the people in charge'),
            OnboardingStep::Schedules => __('Set when each round happens'),
            OnboardingStep::FirstReport => __('Send the first report'),
        };
    }

    private function descriptionFor(OnboardingStep $step): string
    {
        return match ($step) {
            OnboardingStep::Templates => __('Install a ready-made form from the catalog. You can change it afterwards: it is yours.'),
            OnboardingStep::Sites => __('One per place you want to control, with its time zone. The rounds close at the hour of each branch.'),
            OnboardingStep::Team => __('Whoever fills the report and whoever reviews it. Each one sees only their branches.'),
            OnboardingStep::Schedules => __('Daily, weekly or monthly. From here on the pending items appear by themselves.'),
            OnboardingStep::FirstReport => __('Do it yourself once, from the phone. It is the fastest way to see what your team will see.'),
        };
    }

    private function callToActionFor(OnboardingStep $step): string
    {
        return match ($step) {
            OnboardingStep::Templates => __('Open the catalog'),
            OnboardingStep::Sites => __('Add a branch'),
            OnboardingStep::Team => __('Invite someone'),
            OnboardingStep::Schedules => __('Schedule a round'),
            OnboardingStep::FirstReport => __('See the pending items'),
        };
    }
}
