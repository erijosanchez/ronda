<?php

declare(strict_types=1);

namespace Ronda\Insights\Application\Data;

/**
 * Que se quiere exportar. RONDA-PLAN-MAESTRO.md sec. 13
 *
 * Las fechas son dias de sede (`Y-m-d`), como en los KPI.
 *
 * `siteIds` NO es un filtro que elige el usuario: son las sedes que alcanzaba
 * en el momento de encargar la exportacion. El job corre sin sesion, asi que si
 * no se congelan aqui, no habria frontera por sede que aplicar.
 */
final readonly class ExportRequest
{
    /**
     * @param  list<int>  $siteIds  sedes visibles para quien lo encarga
     */
    public function __construct(
        public string $from,
        public string $to,
        public array $siteIds,
        public ?int $siteId = null,
        public ?int $templateId = null,
        public ?string $state = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'site_ids' => $this->siteIds,
            'site_id' => $this->siteId,
            'template_id' => $this->templateId,
            'state' => $this->state,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function fromArray(array $filters): self
    {
        /** @var list<int> $siteIds */
        $siteIds = array_values(array_map(intval(...), is_array($filters['site_ids'] ?? null) ? $filters['site_ids'] : []));

        return new self(
            from: (string) ($filters['from'] ?? ''),
            to: (string) ($filters['to'] ?? ''),
            siteIds: $siteIds,
            siteId: isset($filters['site_id']) ? (int) $filters['site_id'] : null,
            templateId: isset($filters['template_id']) ? (int) $filters['template_id'] : null,
            state: isset($filters['state']) ? (string) $filters['state'] : null,
        );
    }
}
