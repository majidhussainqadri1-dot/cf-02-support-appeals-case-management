<?php

declare(strict_types=1);

namespace Sabri\CF02\Staffing;

enum StaffingRole: string
{
    case SupportAgent = 'support_agent';
    case SpecialistAgent = 'specialist_agent';
    case TeamLead = 'team_lead';
    case AppealReviewer = 'appeal_reviewer';
    case SensitiveLiaison = 'sensitive_liaison';
    case Auditor = 'auditor';
}
