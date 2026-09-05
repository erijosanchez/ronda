<?php

declare(strict_types=1);

namespace Ronda\Identity\Domain;

/**
 * Permisos granulares. RONDA-PLAN-MAESTRO.md sec. 10.3
 *
 * Se agrupan en roles, pero la Policy comprueba el PERMISO, nunca el rol: asi
 * un tenant puede reorganizar sus roles sin tocar codigo.
 *
 * Formato `recurso.verbo`, en singular.
 */
enum PermissionName: string
{
    case UserView = 'user.view';
    case UserManage = 'user.manage';

    case SiteView = 'site.view';
    case SiteManage = 'site.manage';

    case TemplateView = 'template.view';
    case TemplateManage = 'template.manage';
    case TemplatePublish = 'template.publish';

    case ScheduleView = 'schedule.view';
    case ScheduleManage = 'schedule.manage';

    case SubmissionView = 'submission.view';
    case SubmissionCreate = 'submission.create';
    case SubmissionReview = 'submission.review';
    case SubmissionApprove = 'submission.approve';

    case EvidenceView = 'evidence.view';
    case EvidenceUpload = 'evidence.upload';

    case ReportView = 'report.view';

    public function label(): string
    {
        return __('permissions.'.$this->value);
    }
}
