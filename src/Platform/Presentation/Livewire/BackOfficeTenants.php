<?php

declare(strict_types=1);

namespace Ronda\Platform\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Ronda\Platform\Domain\Models\Tenant;

/**
 * La lista de clientes de Ronda. RONDA-PLAN-MAESTRO.md sec. 15.4
 *
 * Lee SOLO la base central: nombre, dominio, estado, plan y cobros. Contar
 * sedes o envios obligaria a entrar en la base de cada cliente, y una lista de
 * cien clientes serian cien conexiones para pintar una tabla. Ese detalle esta
 * en la ficha de cada uno, que entra en una sola.
 *
 * Pagina (regla 5).
 */
#[Layout('platform::back-office.layout')]
final class BackOfficeTenants extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    public function updated(string $property): void
    {
        if ($property === 'search' || $property === 'status') {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $consulta = Tenant::query()
            ->with(['plan', 'domains'])
            ->when($this->search !== '', function ($q): void {
                // `ilike` es de PostgreSQL y busca sin distinguir mayusculas
                // sin necesidad de SQL a mano: el valor va como parametro.
                $texto = '%'.trim($this->search).'%';
                $q->where(function ($busqueda) use ($texto): void {
                    $busqueda->where('name', 'ilike', $texto)
                        ->orWhere('slug', 'ilike', $texto);
                });
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->latest('created_at');

        return view('platform::back-office.tenants', [
            'tenants' => $consulta->paginate(25),
            'states' => Tenant::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }
}
