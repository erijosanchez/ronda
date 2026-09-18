<?php

declare(strict_types=1);

namespace Ronda\Forms\Domain;

/**
 * Las cinco plantillas de arranque. RONDA-PLAN-MAESTRO.md sec. 3.5
 *
 * «Cada una es configuracion del motor, no codigo»: aqui solo hay esquemas de
 * campos, de los mismos tipos que ofrece el disenador. Instalar una crea una
 * plantilla normal y corriente, que el cliente puede editar y versionar como
 * cualquier otra. Nada del resto del sistema sabe que salieron de aqui.
 *
 * Por que existen: un cliente que entra y ve un disenador en blanco tarda
 * semanas en arrancar. Con esto pide su primer arqueo el mismo dia.
 */
enum CatalogTemplate: string
{
    case CashCount = 'ARQUEO-CAJA';
    case BankDeposit = 'DEPOSITO';
    case OpeningClosing = 'APERTURA-CIERRE';
    case Incident = 'INCIDENCIA';
    case CleaningChecklist = 'LIMPIEZA';

    public function label(): string
    {
        return __('catalog-templates.'.$this->value.'.name');
    }

    public function description(): string
    {
        return __('catalog-templates.'.$this->value.'.description');
    }

    /**
     * Como se suele programar. Es una sugerencia para la pantalla: la
     * periodicidad de verdad se decide al crear la programacion.
     */
    public function suggestedCadence(): string
    {
        return __('catalog-templates.'.$this->value.'.cadence');
    }

    /**
     * Los campos de la plantilla, en el formato que entiende FormSchema.
     *
     * @return list<array<string, mixed>>
     */
    public function schema(): array
    {
        return match ($this) {
            self::CashCount => $this->cashCount(),
            self::BankDeposit => $this->bankDeposit(),
            self::OpeningClosing => $this->openingClosing(),
            self::Incident => $this->incident(),
            self::CleaningChecklist => $this->cleaningChecklist(),
        };
    }

    /**
     * Arqueo de caja. La diferencia se escribe a mano: el motor de formulas
     * todavia no calcula campos, y un arqueo sin diferencia declarada no sirve
     * de nada.
     *
     * @return list<array<string, mixed>>
     */
    private function cashCount(): array
    {
        return [
            ['key' => 'conteo', 'type' => 'section', 'label' => 'Conteo de caja'],
            ['key' => 'saldo_sistema', 'type' => 'money', 'label' => 'Saldo segun sistema', 'required' => true, 'reportable' => true],
            ['key' => 'efectivo_contado', 'type' => 'money', 'label' => 'Efectivo contado', 'required' => true, 'reportable' => true],
            [
                'key' => 'diferencia', 'type' => 'money', 'label' => 'Diferencia', 'required' => true, 'reportable' => true,
                'help' => 'Contado menos sistema. Negativa si falta dinero.',
            ],
            [
                'key' => 'motivo_diferencia', 'type' => 'text', 'label' => 'Motivo de la diferencia',
                'visible_when' => ['field' => 'diferencia', 'operator' => 'not_equals', 'value' => '0'],
            ],
            ['key' => 'evidencia', 'type' => 'section', 'label' => 'Evidencia'],
            ['key' => 'foto_arqueo', 'type' => 'photo', 'label' => 'Foto del arqueo', 'required' => true],
            ['key' => 'planilla', 'type' => 'file', 'label' => 'Planilla del arqueo'],
            ['key' => 'observaciones', 'type' => 'text', 'label' => 'Observaciones'],
            ['key' => 'firma', 'type' => 'signature', 'label' => 'Firma de quien entrega', 'required' => true],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function bankDeposit(): array
    {
        return [
            ['key' => 'monto_depositado', 'type' => 'money', 'label' => 'Monto depositado', 'required' => true, 'reportable' => true],
            [
                'key' => 'banco', 'type' => 'select', 'label' => 'Banco', 'required' => true, 'reportable' => true,
                'options' => ['BCP', 'BBVA', 'Interbank', 'Scotiabank', 'Otro'],
            ],
            ['key' => 'numero_operacion', 'type' => 'text', 'label' => 'Numero de operacion', 'required' => true, 'reportable' => true],
            ['key' => 'fecha_deposito', 'type' => 'date', 'label' => 'Fecha del deposito', 'required' => true, 'reportable' => true],
            ['key' => 'voucher', 'type' => 'photo', 'label' => 'Voucher del banco', 'required' => true],
            ['key' => 'observaciones', 'type' => 'text', 'label' => 'Observaciones'],
        ];
    }

    /**
     * Apertura y cierre. Una sola plantilla con dos ventanas: se programa dos
     * veces al dia y el campo `momento` dice cual es. La alarma solo se
     * pregunta al cerrar.
     *
     * @return list<array<string, mixed>>
     */
    private function openingClosing(): array
    {
        return [
            [
                'key' => 'momento', 'type' => 'select', 'label' => 'Momento', 'required' => true, 'reportable' => true,
                'options' => ['Apertura', 'Cierre'],
            ],
            ['key' => 'hora_real', 'type' => 'time', 'label' => 'Hora real', 'required' => true, 'reportable' => true],
            ['key' => 'foto_local', 'type' => 'photo', 'label' => 'Foto del local', 'required' => true, 'help' => 'Tomada en el local: la revision compara la ubicacion.'],
            [
                'key' => 'alarma_activada', 'type' => 'boolean', 'label' => 'Alarma activada', 'reportable' => true,
                'visible_when' => ['field' => 'momento', 'operator' => 'equals', 'value' => 'Cierre'],
            ],
            [
                'key' => 'caja_cerrada', 'type' => 'boolean', 'label' => 'Caja cerrada y asegurada', 'reportable' => true,
                'visible_when' => ['field' => 'momento', 'operator' => 'equals', 'value' => 'Cierre'],
            ],
            ['key' => 'novedades', 'type' => 'text', 'label' => 'Novedades'],
            ['key' => 'firma', 'type' => 'signature', 'label' => 'Firma de quien reporta'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function incident(): array
    {
        return [
            [
                'key' => 'tipo', 'type' => 'select', 'label' => 'Tipo de incidencia', 'required' => true, 'reportable' => true,
                'options' => ['Robo o intento', 'Accidente de persona', 'Falla de equipo', 'Reclamo de cliente', 'Otro'],
            ],
            [
                'key' => 'severidad', 'type' => 'select', 'label' => 'Severidad', 'required' => true, 'reportable' => true,
                'options' => ['Baja', 'Media', 'Alta', 'Critica'],
                'help' => 'Alta y critica avisan al supervisor apenas se entregan.',
            ],
            ['key' => 'fecha_hecho', 'type' => 'date', 'label' => 'Fecha del hecho', 'required' => true, 'reportable' => true],
            ['key' => 'hora_hecho', 'type' => 'time', 'label' => 'Hora del hecho', 'required' => true],
            ['key' => 'descripcion', 'type' => 'text', 'label' => 'Que paso', 'required' => true],
            ['key' => 'fotos', 'type' => 'photo', 'label' => 'Fotos'],
            ['key' => 'hubo_heridos', 'type' => 'boolean', 'label' => 'Hubo personas afectadas', 'required' => true, 'reportable' => true],
            [
                'key' => 'detalle_afectados', 'type' => 'text', 'label' => 'Detalle de las personas afectadas', 'required' => true,
                'visible_when' => ['field' => 'hubo_heridos', 'operator' => 'equals', 'value' => true],
            ],
            ['key' => 'acciones_tomadas', 'type' => 'text', 'label' => 'Acciones tomadas', 'required' => true],
        ];
    }

    /**
     * Checklist de limpieza: una seccion por area, con estado y foto. El
     * puntaje lo pone quien revisa el local, y es lo que alimenta el ranking.
     *
     * @return list<array<string, mixed>>
     */
    private function cleaningChecklist(): array
    {
        $areas = [
            'fachada' => 'Fachada y entrada',
            'atencion' => 'Area de atencion',
            'servicios' => 'Servicios higienicos',
            'almacen' => 'Almacen',
        ];

        $campos = [];

        foreach ($areas as $clave => $nombre) {
            $campos[] = ['key' => $clave, 'type' => 'section', 'label' => $nombre];
            $campos[] = [
                'key' => $clave.'_estado', 'type' => 'select', 'label' => 'Estado', 'required' => true, 'reportable' => true,
                'options' => ['Bueno', 'Regular', 'Malo'],
            ];
            $campos[] = ['key' => $clave.'_foto', 'type' => 'photo', 'label' => 'Foto'];
            $campos[] = [
                'key' => $clave.'_observacion', 'type' => 'text', 'label' => 'Que hay que corregir',
                'visible_when' => ['field' => $clave.'_estado', 'operator' => 'in', 'value' => ['Regular', 'Malo']],
            ];
        }

        $campos[] = ['key' => 'cierre', 'type' => 'section', 'label' => 'Resultado'];
        $campos[] = [
            'key' => 'puntaje', 'type' => 'number', 'label' => 'Puntaje del local', 'required' => true, 'reportable' => true,
            'help' => 'De 0 a 100. Alimenta el ranking de locales.',
        ];
        $campos[] = ['key' => 'comentarios', 'type' => 'text', 'label' => 'Comentarios'];

        return $campos;
    }
}
