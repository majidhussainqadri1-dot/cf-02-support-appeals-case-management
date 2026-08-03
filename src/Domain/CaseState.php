<?php

declare(strict_types=1);

namespace Sabri\CF02\Domain;

enum CaseState: string
{
    case New = 'new';
    case Triaged = 'triaged';
    case InProgress = 'in_progress';
    case WaitingForUser = 'waiting_for_user';
    case WaitingForProvider = 'waiting_for_provider';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Reopened = 'reopened';
}
