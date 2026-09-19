<?php

declare(strict_types=1);

namespace Ronda\Forms\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Ronda\Forms\Application\Actions\InstallCatalogTemplate;
use Ronda\Forms\Domain\CatalogTemplate;
use Ronda\Forms\Domain\Exceptions\InvalidFormSchema;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Identity\Domain\Models\User;
use Ronda\Platform\Domain\Exceptions\PlanLimitExceeded;

/**
 * El catalogo de arranque. RONDA-PLAN-MAESTRO.md sec. 3.5
 *
 * Cinco plantillas listas para instalar. No se instalan solas al crear el
 * cliente a proposito: cada cliente pide unas cosas y no otras, y un catalogo
 * entero volcado en la lista es ruido que hay que ir borrando.
 *
 * Valida, invoca la Action y devuelve (regla 1). La lista es de cinco: no
 * pagina porque no crece.
 */
final class TemplateCatalog extends Component
{
    public function install(string $code): void
    {
        // Publicar es lo que pone la plantilla a producir obligaciones, asi que
        // instalar pide el mismo permiso.
        $this->authorize('publish', Template::class);

        $catalogo = CatalogTemplate::tryFrom($code);

        if (! $catalogo instanceof CatalogTemplate) {
            $this->addError('catalog', __('That template is not in the catalog.'));

            return;
        }

        /** @var User $publisher */
        $publisher = auth()->user();

        try {
            resolve(InstallCatalogTemplate::class)($catalogo, $publisher);
        } catch (InvalidFormSchema $e) {
            $this->addError('catalog', $e->getMessage());

            return;
        } catch (PlanLimitExceeded $e) {
            // Llegar al limite no es un error del cliente: es una conversacion
            // comercial, y se le dice con el numero exacto de su plan.
            $this->addError('catalog', __('Your plan allows :limit templates. Retire one or move up a plan.', [
                'limit' => $e->limitValue,
            ]));

            return;
        }

        session()->flash('status', __('«:template» installed. Schedule it to start asking for it.', [
            'template' => $catalogo->label(),
        ]));

        $this->redirectRoute('templates.index', navigate: true);
    }

    public function render(): View
    {
        $this->authorize('viewAny', Template::class);

        $instaladas = Template::query()
            ->whereIn('code', array_map(static fn (CatalogTemplate $c): string => $c->value, CatalogTemplate::cases()))
            ->pluck('id', 'code');

        return view('forms::templates.catalog', [
            'catalog' => CatalogTemplate::cases(),
            'installed' => $instaladas,
            'canInstall' => auth()->user()?->can('publish', Template::class) ?? false,
        ]);
    }
}
