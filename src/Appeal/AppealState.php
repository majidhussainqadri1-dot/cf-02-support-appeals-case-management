<?php

declare(strict_types=1);

namespace Sabri\CF02\Appeal;

enum AppealState: string
{
    case Submitted = 'submitted';
    case EligibilityReview = 'eligibility_review';
    case Rejected = 'rejected';
    case Accepted = 'accepted';
    case UnderReview = 'under_review';
    case NativeDecisionPending = 'native_decision_pending';
    case Decided = 'decided';
    case Implemented = 'implemented';
    case Closed = 'closed';
    case Reopened = 'reopened';
}
