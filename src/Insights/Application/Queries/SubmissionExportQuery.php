<?php

declare(strict_types=1);

namespace Ronda\Insights\Application\Queries;

use Generator;
use Illuminate\Database\Eloquent\Builder;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Forms\Domain\ValueObjects\Field;
use Ronda\Insights\Application\Data\ExportRequest;
use Ronda\Submissions\Domain\Models\Submission;
use Ronda\Submissions\Domain\Models\SubmissionValue;

/**
 * Las filas de una exportacion de envios. RONDA-PLAN-MAESTRO.md sec. 13
 *
 * Devuelve un generador y recorre la consulta por lotes (`chunkById`): 50 000
 * envios no caben en memoria de golpe, y el escritor de XLSX consume en
 * streaming.
 *
 * La frontera por sede NO sale del scope global: el job corre sin sesion. Sale
 * de la lista de sedes que se congelo al encargar la exportacion.
 *
 * Los valores reportables se leen de `submission_values`, que es justo para lo
 * que existe (ADR 0012): columnas tipadas, sin tocar el JSONB.
 */
final readonly class SubmissionExportQuery
{
    private const int CHUNK = 500;

    /**
     * Las columnas fijas, en orden.
     *
     * @return list<string>
     */
    public function headings(ExportRequest $request): array
    {
        $columnas = [
            __('Date'), __('Site'), __('Template'), __('Version'), __('Status'),
            __('Submitted'), __('On time'), __('Minutes late'), __('Author'),
            __('Reviewer'), __('Decided'), __('Revision'),
        ];

        foreach ($this->reportableFields($request) as $field) {
            $columnas[] = $field->label;
        }

        return $columnas;
    }

    /**
     * @return Generator<int, list<string>>
     */
    public function rows(ExportRequest $request): Generator
    {
        $campos = $this->reportableFields($request);

        foreach ($this->query($request)->lazyById(self::CHUNK) as $envio) {
            /** @var Submission $envio */
            $sede = $envio->site;
            $zona = $sede instanceof Site ? $sede->timezone : 'UTC';
            // Ya vienen cargados con el lote: sin esto seria una consulta por
            // envio, y son decenas de miles.
            $valores = $envio->values->keyBy('field_key');

            $fila = [
                (string) $envio->obligation?->occurrence_date?->toDateString(),
                (string) $sede?->name,
                (string) $envio->templateVersion?->template?->name,
                (string) $envio->templateVersion?->number,
                __('submission-states.'.$envio->state->getValue()),
                $envio->submitted_at->setTimezone($zona)->format('Y-m-d H:i'),
                $envio->is_late ? __('No') : __('Yes'),
                (string) $envio->minutes_late,
                (string) $envio->author?->name,
                (string) $envio->reviewer?->name,
                $envio->reviewed_at?->setTimezone($zona)->format('Y-m-d H:i') ?? '',
                (string) $envio->revision,
            ];

            foreach ($campos as $field) {
                $fila[] = $this->display($valores->get($field->key));
            }

            yield $fila;
        }
    }

    /**
     * @return Builder<Submission>
     */
    private function query(ExportRequest $request): Builder
    {
        return Submission::query()
            ->with([
                'site:id,name,timezone', 'templateVersion.template:id,name',
                'author:id,name', 'reviewer:id,name', 'obligation:id,occurrence_date',
                'values:id,submission_id,field_key,value_text,value_numeric,value_date,value_bool',
            ])
            ->whereIn('site_id', $request->siteIds)
            ->when($request->siteId !== null, fn (Builder $q): Builder => $q->where('site_id', $request->siteId))
            ->when($request->templateId !== null, fn (Builder $q): Builder => $q->where('template_id', $request->templateId))
            ->when($request->state !== null, fn (Builder $q): Builder => $q->where('state', $request->state))
            // Por el dia de la obligacion, que es el dia del que habla el
            // reporte, no por cuando se entrego.
            ->whereHas('obligation', fn (Builder $q): Builder => $q->whereBetween('occurrence_date', [$request->from, $request->to]))
            ->orderBy('id');
    }

    /**
     * Un valor reportable como texto. Cada tipo de campo vive en su propia
     * columna tipada (ADR 0012), asi que se toma la que tenga contenido.
     */
    private function display(?SubmissionValue $valor): string
    {
        if (! $valor instanceof SubmissionValue) {
            return '';
        }

        return match (true) {
            $valor->value_text !== null => $valor->value_text,
            $valor->value_numeric !== null => (string) $valor->value_numeric,
            $valor->value_date !== null => $valor->value_date->toDateString(),
            $valor->value_bool !== null => $valor->value_bool ? __('Yes') : __('No'),
            default => '',
        };
    }

    /**
     * Las columnas de datos solo aparecen si se exporta UNA plantilla: dos
     * plantillas distintas no comparten campos, y mezclarlas daria una hoja con
     * media tabla vacia.
     *
     * @return list<Field>
     */
    private function reportableFields(ExportRequest $request): array
    {
        if ($request->templateId === null) {
            return [];
        }

        $template = Template::query()->with('currentVersion')->find($request->templateId);

        return $template?->currentVersion?->formSchema()->reportableFields() ?? [];
    }
}
