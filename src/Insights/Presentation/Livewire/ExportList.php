<?php

declare(strict_types=1);

namespace Ronda\Insights\Presentation\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Identity\Domain\Models\User;
use Ronda\Insights\Application\Actions\RequestExport;
use Ronda\Insights\Application\Data\ExportRequest;
use Ronda\Insights\Domain\Models\Export;
use Ronda\Submissions\Domain\States\Approved;
use Ronda\Submissions\Domain\States\Rejected;
use Ronda\Submissions\Domain\States\Submitted;
use Ronda\Submissions\Domain\States\UnderReview;

/**
 * Pedir una exportacion y ver las que ya se pidieron.
 * RONDA-PLAN-MAESTRO.md sec. 13
 *
 * Valida, invoca la Action y devuelve (regla 1). El archivo lo escribe un job:
 * aqui no se genera nada, solo se encarga.
 *
 * Solo se ven las propias (ExportPolicy): el archivo se calculo con las sedes
 * que alcanzaba quien lo pidio.
 */
final class ExportList extends Component
{
    use WithPagination;

    /** @var list<string> */
    private const array STATES = ['submitted', 'under_review', 'approved', 'rejected'];

    public string $from = '';

    public string $to = '';

    public string $siteId = '';

    public string $templateId = '';

    public string $state = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Export::class);

        $hoy = CarbonImmutable::now('UTC');
        $this->from = $hoy->startOfMonth()->toDateString();
        $this->to = $hoy->toDateString();
    }

    public function request(): void
    {
        $this->authorize('create', Export::class);

        $this->validate();

        /** @var User $requester */
        $requester = auth()->user();

        // Las sedes que esta persona alcanza AHORA, congeladas en el encargo:
        // el job corre sin sesion (ver ExportRequest).
        /** @var list<int> $sedes */
        $sedes = Site::query()->pluck('id')->map(intval(...))->all();

        resolve(RequestExport::class)(
            new ExportRequest(
                from: $this->from,
                to: $this->to,
                siteIds: $sedes,
                siteId: $this->siteId === '' ? null : (int) $this->siteId,
                templateId: $this->templateId === '' ? null : (int) $this->templateId,
                state: $this->state === '' ? null : $this->state,
            ),
            $requester,
        );

        session()->flash('status', __('Export requested. You will get a notification when the file is ready.'));

        $this->resetPage();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'siteId' => ['nullable', Rule::exists('sites', 'id')->whereNull('deleted_at')],
            'templateId' => ['nullable', Rule::exists('templates', 'id')->whereNull('deleted_at')],
            'state' => ['nullable', Rule::in(self::STATES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'from' => __('start date'),
            'to' => __('end date'),
            'siteId' => __('site'),
            'templateId' => __('template'),
            'state' => __('status'),
        ];
    }

    public function render(): View
    {
        $this->authorize('viewAny', Export::class);

        $exportaciones = $this->exports();

        return view('insights::exports', [
            'exports' => $exportaciones,
            // Mientras haya alguna en marcha, la pantalla se refresca sola.
            'running' => $exportaciones->contains(fn (Export $export): bool => $export->status->isRunning()),
            'sites' => Site::query()->orderBy('name')->get(['id', 'name']),
            'templates' => Template::query()->orderBy('name')->get(['id', 'name']),
            'states' => [Submitted::$name, UnderReview::$name, Approved::$name, Rejected::$name],
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Export>
     */
    private function exports(): LengthAwarePaginator
    {
        return Export::query()
            ->where('requested_by', auth()->id())
            ->latest('id')
            ->paginate(10);
    }
}
