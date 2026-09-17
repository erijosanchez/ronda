<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Presentation\Livewire;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Directory\Domain\Models\Zone;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Forms\Domain\TemplateStatus;
use Ronda\Scheduling\Application\Actions\LaunchSchedule;
use Ronda\Scheduling\Application\Actions\UpdateSchedule;
use Ronda\Scheduling\Application\Data\ScheduleData;
use Ronda\Scheduling\Domain\Models\Schedule;
use Ronda\Scheduling\Domain\RecurrenceFrequency;
use Ronda\Scheduling\Domain\ScheduleScope;
use Ronda\Scheduling\Domain\ValueObjects\RecurrencePattern;

/**
 * Alta y edicion de una programacion. RONDA-PLAN-MAESTRO.md sec. 9.1
 *
 * Valida, invoca la Action y devuelve (regla 1). La repeticion se elige con
 * controles (diaria, semanal, mensual) y RecurrencePattern la convierte en la
 * RRULE que guarda la base; solo quien lo necesita escribe la regla a mano.
 *
 * Muestra las proximas fechas mientras se edita: una regla se entiende mucho
 * mejor viendo en que dias cae que leyendola.
 */
final class ScheduleForm extends Component
{
    use WithPagination;

    /** Cuantas fechas de muestra se ensenan. */
    private const int PREVIEW_DATES = 6;

    public ?Schedule $schedule = null;

    public string $templateId = '';

    public string $name = '';

    public string $scope = 'all_sites';

    public string $zoneId = '';

    /** @var list<string> */
    public array $siteIds = [];

    public string $siteSearch = '';

    public string $frequency = 'daily';

    public string $interval = '1';

    /** @var list<string> */
    public array $weekdays = ['MO', 'TU', 'WE', 'TH', 'FR'];

    public string $monthDay = '1';

    public string $customRule = '';

    public string $windowStart = '08:00';

    public string $windowEnd = '18:00';

    public string $toleranceMinutes = '0';

    public bool $skipHolidays = true;

    public string $startsOn = '';

    public string $endsOn = '';

    public function mount(?Schedule $schedule = null): void
    {
        if ($schedule instanceof Schedule && $schedule->exists) {
            $this->authorize('update', $schedule);
            $this->fillFrom($schedule);

            return;
        }

        $this->authorize('create', Schedule::class);
        $this->startsOn = CarbonImmutable::today()->toDateString();
    }

    public function updatedSiteSearch(): void
    {
        $this->resetPage('sedes');
    }

    public function save(): void
    {
        $this->schedule instanceof Schedule
            ? $this->authorize('update', $this->schedule)
            : $this->authorize('create', Schedule::class);

        $this->validate();

        if (! $this->sitesAreReachable()) {
            $this->addError('siteIds', __('Some of the selected sites are not available to you.'));

            return;
        }

        // El dominio tiene la ultima palabra sobre la regla y la programacion:
        // su excepcion se muestra como error de formulario en vez de reventar
        // la pantalla.
        try {
            $data = new ScheduleData(
                templateId: (int) $this->templateId,
                name: trim($this->name),
                scope: ScheduleScope::from($this->scope),
                rrule: $this->pattern()->toRecurrence()->rule,
                windowStart: $this->windowStart,
                windowEnd: $this->windowEnd,
                startsOn: $this->startsOn,
                endsOn: trim($this->endsOn) === '' ? null : $this->endsOn,
                toleranceMinutes: (int) $this->toleranceMinutes,
                skipHolidays: $this->skipHolidays,
                zoneId: $this->zoneId === '' ? null : (int) $this->zoneId,
                siteIds: array_values(array_unique(array_map(intval(...), $this->siteIds))),
            );

            $this->schedule instanceof Schedule
                ? resolve(UpdateSchedule::class)($this->schedule, $data)
                : resolve(LaunchSchedule::class)($data);
        } catch (DomainException $e) {
            $this->addError('schedule', $e->getMessage());

            return;
        }

        session()->flash('status', $this->schedule instanceof Schedule
            ? __('Schedule updated.')
            : __('Schedule created. Its obligations for the coming days are ready.'));

        $this->redirectRoute('schedules.index', navigate: true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'templateId' => [
                'required',
                Rule::exists('templates', 'id')
                    ->where('status', TemplateStatus::Published->value)
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'scope' => ['required', Rule::enum(ScheduleScope::class)],
            'zoneId' => [
                Rule::requiredIf($this->scope === ScheduleScope::Zone->value),
                'nullable',
                Rule::exists('zones', 'id')->whereNull('deleted_at'),
            ],
            'siteIds' => [Rule::requiredIf($this->scope === ScheduleScope::Sites->value), 'array'],
            'siteIds.*' => ['integer'],
            'frequency' => ['required', Rule::enum(RecurrenceFrequency::class)],
            'interval' => ['required', 'integer', 'min:1', 'max:365'],
            'weekdays' => [Rule::requiredIf($this->frequency === RecurrenceFrequency::Weekly->value), 'array'],
            'weekdays.*' => [Rule::in(RecurrencePattern::WEEKDAYS)],
            'monthDay' => [
                Rule::requiredIf($this->frequency === RecurrenceFrequency::Monthly->value),
                Rule::in(array_map(strval(...), [RecurrencePattern::LAST_DAY_OF_MONTH, ...range(1, 31)])),
            ],
            'customRule' => [
                Rule::requiredIf($this->frequency === RecurrenceFrequency::Custom->value),
                'nullable', 'string', 'max:500',
            ],
            'windowStart' => ['required', 'date_format:H:i'],
            // La ventana no cruza la medianoche (ver TimeWindow).
            'windowEnd' => ['required', 'date_format:H:i', 'after:windowStart'],
            'toleranceMinutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'skipHolidays' => ['boolean'],
            'startsOn' => ['required', 'date_format:Y-m-d'],
            'endsOn' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:startsOn'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'templateId' => __('template'),
            'name' => __('name'),
            'scope' => __('scope'),
            'zoneId' => __('zone'),
            'siteIds' => __('sites'),
            'frequency' => __('repeat'),
            'interval' => __('interval'),
            'weekdays' => __('days of the week'),
            'monthDay' => __('day of the month'),
            'customRule' => __('rule'),
            'windowStart' => __('opening time'),
            'windowEnd' => __('due time'),
            'toleranceMinutes' => __('tolerance'),
            'startsOn' => __('start date'),
            'endsOn' => __('end date'),
        ];
    }

    public function render(): View
    {
        $editing = $this->schedule instanceof Schedule;

        return view('scheduling::schedules.form', [
            'editing' => $editing,
            'templates' => $this->templates($editing),
            'zones' => Zone::query()->orderBy('name')->get(['id', 'name']),
            'sites' => $this->scope === ScheduleScope::Sites->value ? $this->sites() : null,
            'scopes' => ScheduleScope::cases(),
            'frequencies' => RecurrenceFrequency::cases(),
            'weekdayOptions' => RecurrencePattern::WEEKDAYS,
            'preview' => $this->previewDates(),
        ]);
    }

    private function fillFrom(Schedule $schedule): void
    {
        $this->schedule = $schedule;
        $this->templateId = (string) $schedule->template_id;
        $this->name = $schedule->name;
        $this->scope = $schedule->scope->value;
        $this->zoneId = $schedule->zone_id === null ? '' : (string) $schedule->zone_id;
        $this->siteIds = array_values(array_map(strval(...), $schedule->sites()->withoutGlobalScopes()->pluck('sites.id')->all()));
        $this->windowStart = mb_substr($schedule->window_start, 0, 5);
        $this->windowEnd = mb_substr($schedule->window_end, 0, 5);
        $this->toleranceMinutes = (string) $schedule->tolerance_minutes;
        $this->skipHolidays = $schedule->skip_holidays;
        $this->startsOn = $schedule->starts_on->toDateString();
        $this->endsOn = $schedule->ends_on?->toDateString() ?? '';

        $pattern = RecurrencePattern::fromRecurrence($schedule->recurrence());

        $this->frequency = $pattern->frequency->value;
        $this->interval = (string) $pattern->interval;
        $this->customRule = (string) $pattern->customRule;

        if ($pattern->frequency === RecurrenceFrequency::Weekly) {
            $this->weekdays = array_values($pattern->weekdays);
        }

        if ($pattern->monthDay !== null) {
            $this->monthDay = (string) $pattern->monthDay;
        }
    }

    private function pattern(): RecurrencePattern
    {
        $intervalo = max(1, (int) $this->interval);

        return match (RecurrenceFrequency::from($this->frequency)) {
            RecurrenceFrequency::Daily => RecurrencePattern::daily($intervalo),
            RecurrenceFrequency::Weekly => RecurrencePattern::weekly(array_values($this->weekdays), $intervalo),
            RecurrenceFrequency::Monthly => RecurrencePattern::monthly((int) $this->monthDay, $intervalo),
            RecurrenceFrequency::Custom => RecurrencePattern::custom($this->customRule),
        };
    }

    /**
     * Las proximas fechas con lo que hay escrito ahora mismo, o null si todavia
     * no forma una regla valida. No descuenta feriados: la vista lo avisa.
     *
     * @return list<string>|null
     */
    private function previewDates(): ?array
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->startsOn) !== 1
            || RecurrenceFrequency::tryFrom($this->frequency) === null) {
            return null;
        }

        try {
            $desde = max($this->startsOn, CarbonImmutable::today()->toDateString());
            $fechas = $this->pattern()->toRecurrence()->nextDates($this->startsOn, $desde, self::PREVIEW_DATES);
        } catch (DomainException) {
            return null;
        }

        if ($this->endsOn !== '') {
            return array_values(array_filter($fechas, fn (string $fecha): bool => $fecha <= $this->endsOn));
        }

        return $fechas;
    }

    /**
     * Las plantillas que se pueden programar. Al editar la plantilla no cambia,
     * pero se incluye la actual aunque ya no este publicada para que el
     * formulario la muestre.
     *
     * @return iterable<Template>
     */
    private function templates(bool $editing): iterable
    {
        return Template::query()
            ->when(
                $editing,
                fn ($query) => $query->whereKey((int) $this->templateId),
                fn ($query) => $query->where('status', TemplateStatus::Published->value),
            )
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    /**
     * El selector de sedes pagina: un cliente con trescientas sedes no cabe en
     * una lista de casillas (regla 5). Las marcadas se conservan al cambiar de
     * pagina porque viven en `siteIds`, no en la pagina.
     *
     * @return LengthAwarePaginator<int, Site>
     */
    private function sites(): LengthAwarePaginator
    {
        return Site::query()
            ->when($this->siteSearch !== '', function ($query): void {
                $termino = '%'.$this->siteSearch.'%';

                $query->where(function ($q) use ($termino): void {
                    $q->where('name', 'ilike', $termino)
                        ->orWhere('code', 'ilike', $termino);
                });
            })
            ->orderBy('name')
            ->paginate(10, ['id', 'code', 'name'], 'sedes');
    }

    /**
     * Frontera por sede: solo se programan sedes que este usuario alcanza.
     * Site::query() lleva AssignedSitesScope, asi que un id ajeno no se cuenta.
     */
    private function sitesAreReachable(): bool
    {
        if ($this->scope !== ScheduleScope::Sites->value) {
            return true;
        }

        $ids = array_values(array_unique(array_map(intval(...), $this->siteIds)));

        return Site::query()->whereKey($ids)->count() === count($ids);
    }
}
