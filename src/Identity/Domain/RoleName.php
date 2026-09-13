<?php

declare(strict_types=1);

namespace Ronda\Identity\Domain;

/**
 * Roles predefinidos que recibe todo tenant al provisionarse.
 * RONDA-PLAN-MAESTRO.md sec. 10.3
 *
 * El tenant puede clonarlos y ajustarlos; estos cuatro son el punto de partida
 * y no se borran.
 *
 * Los identificadores van en ingles por la convencion del proyecto (codigo en
 * ingles, interfaz en espanol). El nombre que ve el usuario sale de `label()`,
 * que traduce: `site_manager` se muestra como «Encargado».
 */
enum RoleName: string
{
    /** Dueno de la cuenta del cliente. Gate::before le concede todo (sec. 10.3). */
    case Owner = 'owner';

    /** Administra usuarios, sedes y plantillas. No toca facturacion. */
    case Admin = 'admin';

    /** Revisa y aprueba los envios de las sedes a su cargo. */
    case Supervisor = 'supervisor';

    /** Responsable de una sede: rellena y envia. */
    case SiteManager = 'site_manager';

    public function label(): string
    {
        return __('roles.'.$this->value);
    }

    /**
     * Permisos que trae cada rol al provisionar un tenant.
     *
     * `Owner` recibe la lista completa, derivada de PermissionName::cases(), asi
     * que no hay nada que mantener sincronizado a mano. Ademas pasa por
     * `Gate::before` (OwnerGate), que cubre las habilidades que no se
     * corresponden con un permiso, como las condiciones propias de una Policy.
     *
     * @return list<PermissionName>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::Owner => PermissionName::cases(),

            self::Admin => [
                PermissionName::UserView,
                PermissionName::UserManage,
                PermissionName::SiteView,
                PermissionName::SiteManage,
                PermissionName::TemplateView,
                PermissionName::TemplateManage,
                PermissionName::TemplatePublish,
                PermissionName::ScheduleView,
                PermissionName::ScheduleManage,
                PermissionName::SubmissionView,
                PermissionName::EvidenceView,
                PermissionName::ReportView,
            ],

            self::Supervisor => [
                PermissionName::SiteView,
                PermissionName::TemplateView,
                PermissionName::ScheduleView,
                PermissionName::SubmissionView,
                PermissionName::SubmissionReview,
                PermissionName::SubmissionApprove,
                PermissionName::EvidenceView,
                PermissionName::ReportView,
            ],

            self::SiteManager => [
                PermissionName::SiteView,
                PermissionName::TemplateView,
                PermissionName::ScheduleView,
                PermissionName::SubmissionView,
                PermissionName::SubmissionCreate,
                PermissionName::EvidenceView,
                PermissionName::EvidenceUpload,
            ],
        };
    }
}
