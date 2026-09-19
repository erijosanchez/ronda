<?php

declare(strict_types=1);

namespace Ronda\Platform\Application\Queries;

use Ronda\Directory\Domain\Models\Site;
use Ronda\Evidence\Domain\Models\Attachment;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Identity\Domain\Models\User;
use Ronda\Platform\Domain\ValueObjects\PlanUsage;
use Ronda\Platform\Domain\ValueObjects\SiteStorage;

/**
 * Cuanto esta usando el cliente de lo que su plan le da.
 * RONDA-PLAN-MAESTRO.md sec. 3.6 y 15.1
 *
 * Se calcula al preguntar y no se guarda: el uso cambia con cada envio, y una
 * cifra cacheada en la pantalla del plan es una cifra que alguien discute a fin
 * de mes. `usage_metrics` (sec. 8.2) llega con la facturacion, para tener el
 * historico diario que se cobra; esto es la foto de ahora.
 *
 * Las sedes se cuentan sin la frontera por sede: el plan se factura por el
 * parque entero, no por lo que alcance quien esta mirando. Quien abre esta
 * pantalla ya paso por la Policy.
 *
 * El espacio se saca en una sola pasada agrupada y no por sede: con cien sedes,
 * una consulta por sede es cien consultas para pintar una tabla.
 */
final readonly class PlanUsageQuery
{
    public function __invoke(): PlanUsage
    {
        /** @var array<int, int> $bytes */
        $bytes = Attachment::query()
            ->join('submissions', 'submissions.id', '=', 'attachments.submission_id')
            ->groupBy('submissions.site_id')
            ->selectRaw('submissions.site_id as site_id, sum(attachments.bytes) as total')
            ->pluck('total', 'site_id')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();

        /** @var list<SiteStorage> $porSede */
        $porSede = Site::query()
            ->withoutGlobalScopes()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Site $sede): SiteStorage => new SiteStorage(
                siteId: (int) $sede->id,
                name: (string) $sede->name,
                bytes: $bytes[(int) $sede->id] ?? 0,
            ))
            ->all();

        return new PlanUsage(
            sites: count($porSede),
            users: User::query()->count(),
            templates: Template::query()->count(),
            storageBySite: $porSede,
        );
    }
}
