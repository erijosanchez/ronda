<?php

declare(strict_types=1);

namespace Ronda\Identity\Application\Import;

use Illuminate\Database\ConnectionInterface;
use Ronda\Identity\Application\Data\PlannedUser;
use Ronda\Identity\Application\Data\UserData;
use Ronda\Identity\Application\Data\UserImportPlan;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\RoleName;
use Ronda\Identity\Domain\UserStatus;
use Ronda\Platform\Domain\Csv\CsvRow;
use Ronda\Platform\Domain\Csv\CsvTable;
use Ronda\Platform\Domain\Csv\ImportIssue;
use Ronda\Platform\Domain\Csv\ImportIssueReason;
use Ronda\Platform\Domain\Exceptions\UnreadableCsv;

/**
 * Convierte un archivo de personas en un plan.
 * RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * No escribe nada; decide que se haria y que esta mal. Dos limites que no son
 * negociables y que el formulario ya tenia:
 *
 *   - AL PROPIETARIO NO SE LE IMPORTA. UserPolicy::update solo deja que el
 *     propietario se edite a si mismo, justamente para que un administrador no
 *     pueda cambiarle el correo y quedarse con la cuenta. Un CSV que
 *     actualizara su ficha seria esa misma puerta, abierta de par en par.
 *   - EL ROL DE PROPIETARIO NO SE CONCEDE IMPORTANDO. Cambiar de propietario
 *     es una operacion aparte y con auditoria, no una celda en una hoja.
 *
 * Los roles se aceptan por su nombre visible («Encargado») y por su clave
 * («site_manager»): quien llena la hoja copia lo que ve en la pantalla.
 */
final readonly class UserCsvPlanner
{
    private const array REQUIRED_COLUMNS = ['nombre', 'correo'];

    /** Separadores admitidos dentro de una celda con varios valores. */
    private const string MULTIPLE = '/[|\/]/';

    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    /**
     * @throws UnreadableCsv si al archivo le faltan columnas obligatorias
     */
    public function __invoke(CsvTable $table): UserImportPlan
    {
        $faltan = $table->missing(self::REQUIRED_COLUMNS);

        if ($faltan !== []) {
            throw UnreadableCsv::missingColumns($faltan);
        }

        $existentes = $this->existingUsers();
        $eliminados = $this->trashedEmails();
        $duenos = $this->ownerEmails();
        $sedes = $this->siteCodes();
        $roles = $this->roleIndex();

        $planificadas = [];
        $problemas = [];
        $vistos = [];

        foreach ($table->rows as $fila) {
            $correo = mb_strtolower($fila->get('correo'));

            if ($correo !== '' && isset($vistos[$correo])) {
                $problemas[] = new ImportIssue($fila->number, 'correo', ImportIssueReason::Duplicated, $fila->get('correo'));

                continue;
            }

            $vistos[$correo] = true;

            $problemasFila = $this->validate($fila, $roles, $sedes, $eliminados, $duenos);

            if ($problemasFila !== []) {
                $problemas = [...$problemas, ...$problemasFila];

                continue;
            }

            $planificadas[] = new PlannedUser(
                $this->toData($fila, $roles),
                $existentes[$correo] ?? null,
                $this->siteIds($fila, $sedes),
            );
        }

        return new UserImportPlan($planificadas, $problemas);
    }

    /**
     * @param  array<string, RoleName>  $roles
     * @param  array<string, int>  $sites
     * @param  array<string, true>  $trashed
     * @param  array<string, true>  $owners
     * @return list<ImportIssue>
     */
    private function validate(CsvRow $row, array $roles, array $sites, array $trashed, array $owners): array
    {
        $problemas = [];
        $nombre = $row->get('nombre');
        $correo = $row->get('correo');

        if ($nombre === '') {
            $problemas[] = new ImportIssue($row->number, 'nombre', ImportIssueReason::Required);
        } elseif (mb_strlen($nombre) > 255) {
            $problemas[] = new ImportIssue($row->number, 'nombre', ImportIssueReason::TooLong, $nombre);
        }

        if ($correo === '') {
            $problemas[] = new ImportIssue($row->number, 'correo', ImportIssueReason::Required);
        } elseif (filter_var($correo, FILTER_VALIDATE_EMAIL) === false || mb_strlen($correo) > 255) {
            $problemas[] = new ImportIssue($row->number, 'correo', ImportIssueReason::Invalid, $correo);
        } else {
            $clave = mb_strtolower($correo);

            if (isset($trashed[$clave])) {
                // El correo de alguien dado de baja sigue ocupando el indice
                // unico: reutilizarlo reviviria una ficha por la puerta de
                // atras.
                $problemas[] = new ImportIssue($row->number, 'correo', ImportIssueReason::Taken, $correo);
            } elseif (isset($owners[$clave])) {
                $problemas[] = new ImportIssue($row->number, 'correo', ImportIssueReason::Taken, $correo);
            }
        }

        if ($row->has('telefono') && mb_strlen($row->get('telefono')) > 30) {
            $problemas[] = new ImportIssue($row->number, 'telefono', ImportIssueReason::TooLong, $row->get('telefono'));
        }

        if ($row->has('estado') && ! $this->status($row->get('estado')) instanceof UserStatus) {
            $problemas[] = new ImportIssue($row->number, 'estado', ImportIssueReason::Invalid, $row->get('estado'));
        }

        foreach ($this->values($row, 'roles') as $rol) {
            $encontrado = $roles[$this->key($rol)] ?? null;

            if (! $encontrado instanceof RoleName) {
                $problemas[] = new ImportIssue($row->number, 'roles', ImportIssueReason::Invalid, $rol);
            } elseif ($encontrado === RoleName::Owner) {
                $problemas[] = new ImportIssue($row->number, 'roles', ImportIssueReason::NotAllowed, $rol);
            }
        }

        foreach ($this->values($row, 'sedes') as $codigo) {
            if (! isset($sites[mb_strtolower($codigo)])) {
                $problemas[] = new ImportIssue($row->number, 'sedes', ImportIssueReason::NotFound, $codigo);
            }
        }

        return $problemas;
    }

    /**
     * @param  array<string, RoleName>  $roles
     */
    private function toData(CsvRow $row, array $roles): UserData
    {
        $suyos = [];

        foreach ($this->values($row, 'roles') as $rol) {
            $encontrado = $roles[$this->key($rol)] ?? null;

            if ($encontrado instanceof RoleName) {
                $suyos[] = $encontrado;
            }
        }

        return new UserData(
            name: $row->get('nombre'),
            email: mb_strtolower($row->get('correo')),
            status: $row->has('estado')
                ? ($this->status($row->get('estado')) ?? UserStatus::Active)
                : UserStatus::Active,
            roles: array_values(array_unique($suyos, SORT_REGULAR)),
            phone: $row->has('telefono') ? $row->get('telefono') : null,
        );
    }

    /**
     * Las sedes que pide la fila, o null si no pide ninguna.
     *
     * Una celda vacia significa «no toques sus sedes», no «quitaselas todas».
     * Un archivo al que le falta un dato no puede dejar sin acceso a nadie: la
     * asignacion se retira desde su ficha, a conciencia.
     *
     * @param  array<string, int>  $sites
     * @return list<int>|null
     */
    private function siteIds(CsvRow $row, array $sites): ?array
    {
        $codigos = $this->values($row, 'sedes');

        if ($codigos === []) {
            return null;
        }

        $ids = [];

        foreach ($codigos as $codigo) {
            $id = $sites[mb_strtolower($codigo)] ?? null;

            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Los valores de una celda que admite varios, separados por `|` o `/`.
     *
     * @return list<string>
     */
    private function values(CsvRow $row, string $column): array
    {
        if (! $row->has($column)) {
            return [];
        }

        $partes = preg_split(self::MULTIPLE, $row->get($column)) ?: [];

        return array_values(array_filter(array_map(trim(...), $partes), static fn (string $v): bool => $v !== ''));
    }

    private function status(string $value): ?UserStatus
    {
        return match ($this->key($value)) {
            'activo', 'active' => UserStatus::Active,
            'suspendido', 'suspended' => UserStatus::Suspended,
            default => null,
        };
    }

    /**
     * Los roles por clave y por nombre visible.
     *
     * @return array<string, RoleName>
     */
    private function roleIndex(): array
    {
        $indice = [];

        foreach (RoleName::cases() as $rol) {
            $indice[$this->key($rol->value)] = $rol;
            $indice[$this->key($rol->label())] = $rol;
        }

        return $indice;
    }

    /**
     * @return array<string, int>
     */
    private function existingUsers(): array
    {
        $porCorreo = [];

        foreach ($this->connection->table('users')->whereNull('deleted_at')->get(['id', 'email']) as $fila) {
            $porCorreo[mb_strtolower((string) $fila->email)] = (int) $fila->id;
        }

        return $porCorreo;
    }

    /**
     * @return array<string, true>
     */
    private function trashedEmails(): array
    {
        $correos = [];

        foreach ($this->connection->table('users')->whereNotNull('deleted_at')->get(['email']) as $fila) {
            $correos[mb_strtolower((string) $fila->email)] = true;
        }

        return $correos;
    }

    /**
     * Los correos de quien tiene el rol de propietario.
     *
     * Se resuelve con una consulta y no con `hasRole()` porque la comprobacion
     * de rol fuera de una Policy esta prohibida (regla 4). Aqui no se esta
     * autorizando a nadie: se esta averiguando a quien NO se toca.
     *
     * @return array<string, true>
     */
    private function ownerEmails(): array
    {
        // Por la relacion y no por un JOIN a mano: `model_has_roles.model_type`
        // guarda el nombre de clase completo, y escribirlo aqui convertiria un
        // cambio de namespace en un agujero silencioso.
        $filas = User::query()->role(RoleName::Owner->value)->get(['email']);

        $correos = [];

        foreach ($filas as $fila) {
            $correos[mb_strtolower((string) $fila->email)] = true;
        }

        return $correos;
    }

    /**
     * @return array<string, int>
     */
    private function siteCodes(): array
    {
        $porCodigo = [];

        foreach ($this->connection->table('sites')->whereNull('deleted_at')->get(['id', 'code']) as $fila) {
            $porCodigo[mb_strtolower((string) $fila->code)] = (int) $fila->id;
        }

        return $porCodigo;
    }

    private function key(string $value): string
    {
        $sinAcentos = strtr(mb_strtolower(trim($value)), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        ]);

        return (string) preg_replace('/[^a-z0-9]+/', '_', $sinAcentos);
    }
}
