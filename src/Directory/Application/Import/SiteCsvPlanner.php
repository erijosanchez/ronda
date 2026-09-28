<?php

declare(strict_types=1);

namespace Ronda\Directory\Application\Import;

use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Ronda\Directory\Application\Data\PlannedSite;
use Ronda\Directory\Application\Data\SiteData;
use Ronda\Directory\Application\Data\SiteImportPlan;
use Ronda\Directory\Domain\Models\Zone;
use Ronda\Platform\Domain\Csv\CsvRow;
use Ronda\Platform\Domain\Csv\CsvTable;
use Ronda\Platform\Domain\Csv\ImportIssue;
use Ronda\Platform\Domain\Csv\ImportIssueReason;
use Ronda\Platform\Domain\Exceptions\UnreadableCsv;

/**
 * Convierte un archivo de sedes en un plan. RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * No escribe nada. Lee el archivo entero, lo compara con lo que ya hay y
 * devuelve lo que se haria y lo que esta mal. Escribir es cosa de ImportSites,
 * y solo si esto no encontro nada roto.
 *
 * Las reglas son las mismas que las del formulario de una sede, a proposito:
 * si importar admitiera lo que la pantalla rechaza, el CSV seria la puerta de
 * atras por la que entran los datos sucios.
 */
final readonly class SiteCsvPlanner
{
    /** Sin estas no hay nada que importar. */
    private const array REQUIRED_COLUMNS = ['codigo', 'nombre'];

    /** La de Peru: es donde opera el cliente que importa. */
    private const string DEFAULT_TIMEZONE = 'America/Lima';

    public function __construct(
        // La conexion se inyecta en vez de usar la facade DB: la capa de
        // aplicacion no puede depender de facades (CLAUDE.md, regla 8).
        private ConnectionInterface $connection,
    ) {}

    /**
     * @throws UnreadableCsv si al archivo le faltan columnas obligatorias
     */
    public function __invoke(CsvTable $table): SiteImportPlan
    {
        $faltan = $table->missing(self::REQUIRED_COLUMNS);

        if ($faltan !== []) {
            throw UnreadableCsv::missingColumns($faltan);
        }

        $zonas = $this->zones();
        $existentes = $this->existingCodes();
        $eliminadas = $this->trashedCodes();

        $planificadas = [];
        $problemas = [];
        $vistos = [];

        foreach ($table->rows as $fila) {
            $codigo = $fila->get('codigo');
            $clave = mb_strtolower($codigo);

            // El archivo contra si mismo: dos filas con el mismo codigo son un
            // error del archivo, no de Ronda, y la segunda pisaria a la
            // primera sin que nadie se entere.
            if ($clave !== '' && isset($vistos[$clave])) {
                $problemas[] = new ImportIssue($fila->number, 'codigo', ImportIssueReason::Duplicated, $codigo);

                continue;
            }

            $vistos[$clave] = true;

            $problemasFila = $this->validate($fila, $zonas, $eliminadas);

            if ($problemasFila !== []) {
                $problemas = [...$problemas, ...$problemasFila];

                continue;
            }

            $planificadas[] = new PlannedSite(
                $this->toData($fila, $zonas),
                $existentes[$clave] ?? null,
            );
        }

        return new SiteImportPlan($planificadas, $problemas);
    }

    /**
     * @param  array<string, int|null>  $zones  null = el nombre esta repetido
     * @param  array<string, true>  $trashed
     * @return list<ImportIssue>
     */
    private function validate(CsvRow $row, array $zones, array $trashed): array
    {
        $problemas = [];

        foreach (['codigo' => 50, 'nombre' => 255] as $columna => $largo) {
            $valor = $row->get($columna);

            if ($valor === '') {
                $problemas[] = new ImportIssue($row->number, $columna, ImportIssueReason::Required);
            } elseif (mb_strlen($valor) > $largo) {
                $problemas[] = new ImportIssue($row->number, $columna, ImportIssueReason::TooLong, $valor);
            }
        }

        $codigo = mb_strtolower($row->get('codigo'));

        // Un codigo de sede no se recicla: el indice de la base tampoco lo
        // permite, asi que vale mas decirlo aqui que fallar a mitad del
        // INSERT con un error de PostgreSQL.
        if ($codigo !== '' && isset($trashed[$codigo])) {
            $problemas[] = new ImportIssue($row->number, 'codigo', ImportIssueReason::Taken, $row->get('codigo'));
        }

        if ($row->has('zona')) {
            $zona = $this->key($row->get('zona'));

            if (! array_key_exists($zona, $zones)) {
                $problemas[] = new ImportIssue($row->number, 'zona', ImportIssueReason::NotFound, $row->get('zona'));
            } elseif ($zones[$zona] === null) {
                // Dos zonas con el mismo nombre: no hay forma de saber cual.
                $problemas[] = new ImportIssue($row->number, 'zona', ImportIssueReason::Invalid, $row->get('zona'));
            }
        }

        if ($row->has('zona_horaria') && ! in_array($row->get('zona_horaria'), DateTimeZone::listIdentifiers(), true)) {
            $problemas[] = new ImportIssue($row->number, 'zona_horaria', ImportIssueReason::Invalid, $row->get('zona_horaria'));
        }

        foreach (['latitud' => 90, 'longitud' => 180] as $columna => $tope) {
            if (! $row->has($columna)) {
                continue;
            }

            $valor = str_replace(',', '.', $row->get($columna));

            if (! is_numeric($valor) || abs((float) $valor) > $tope) {
                $problemas[] = new ImportIssue($row->number, $columna, ImportIssueReason::Invalid, $row->get($columna));
            }
        }

        foreach (['abre', 'cierra'] as $columna) {
            if ($row->has($columna) && $this->time($row->get($columna)) === null) {
                $problemas[] = new ImportIssue($row->number, $columna, ImportIssueReason::Invalid, $row->get($columna));
            }
        }

        return $problemas;
    }

    /**
     * @param  array<string, int|null>  $zones
     */
    private function toData(CsvRow $row, array $zones): SiteData
    {
        return new SiteData(
            code: $row->get('codigo'),
            name: $row->get('nombre'),
            timezone: $row->has('zona_horaria') ? $row->get('zona_horaria') : self::DEFAULT_TIMEZONE,
            zoneId: $row->has('zona') ? $zones[$this->key($row->get('zona'))] : null,
            address: $row->has('direccion') ? $row->get('direccion') : null,
            latitude: $row->has('latitud') ? str_replace(',', '.', $row->get('latitud')) : null,
            longitude: $row->has('longitud') ? str_replace(',', '.', $row->get('longitud')) : null,
            opensAt: $row->has('abre') ? $this->time($row->get('abre')) : null,
            closesAt: $row->has('cierra') ? $this->time($row->get('cierra')) : null,
        );
    }

    /**
     * La hora en `HH:MM`, o null si no lo es.
     *
     * Se acepta `8:30` ademas de `08:30`: es lo que escribe cualquiera, y
     * rechazarlo por un cero seria pedantería.
     */
    private function time(string $value): ?string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $value, $partes) !== 1) {
            return null;
        }

        $hora = (int) $partes[1];
        $minuto = (int) $partes[2];

        if ($hora > 23 || $minuto > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $hora, $minuto);
    }

    /**
     * Las zonas por nombre. null cuando el nombre esta repetido.
     *
     * @return array<string, int|null>
     */
    private function zones(): array
    {
        $porNombre = [];

        foreach (Zone::query()->get(['id', 'name']) as $zona) {
            $clave = $this->key($zona->name);

            $porNombre[$clave] = array_key_exists($clave, $porNombre)
                ? null
                : (int) $zona->id;
        }

        return $porNombre;
    }

    /**
     * Los codigos que ya existen, con su id.
     *
     * Se consulta la tabla y no el modelo para saltarse el scope de frontera
     * por sede: decidir si una fila es alta o actualizacion no puede depender
     * de a que sedes esta asignada la persona que importa.
     *
     * @return array<string, int>
     */
    private function existingCodes(): array
    {
        $filas = $this->connection->table('sites')->whereNull('deleted_at')->get(['id', 'code']);

        $porCodigo = [];

        foreach ($filas as $fila) {
            $porCodigo[mb_strtolower((string) $fila->code)] = (int) $fila->id;
        }

        return $porCodigo;
    }

    /**
     * Los codigos de sedes eliminadas: siguen ocupando el indice unico.
     *
     * @return array<string, true>
     */
    private function trashedCodes(): array
    {
        $codigos = [];

        foreach ($this->connection->table('sites')->whereNotNull('deleted_at')->get(['code']) as $fila) {
            $codigos[mb_strtolower((string) $fila->code)] = true;
        }

        return $codigos;
    }

    /**
     * «Lima Norte», «lima norte» y «LIMA  NORTE» son la misma zona.
     */
    private function key(string $value): string
    {
        return (string) preg_replace('/\s+/', ' ', mb_strtolower(trim($value)));
    }
}
