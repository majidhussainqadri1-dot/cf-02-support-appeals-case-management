<?php

declare(strict_types=1);

namespace Sabri\CF02\Automation;

enum AutomationAction: string
{
    case Classify = 'classify';
    case Summarize = 'summarize';
    case DraftReply = 'draft_reply';
    case SuggestKnowledge = 'suggest_knowledge';
    case SuggestPriority = 'suggest_priority';
    case FinalAppealDecision = 'final_appeal_decision';
    case IdentityHandover = 'identity_handover';
    case RefundApproval = 'refund_approval';
    case ClinicalAdvice = 'clinical_advice';
    case SafetyClosure = 'safety_closure';
    case NativeMutation = 'native_mutation';
    case AutoClose = 'auto_close';
}
