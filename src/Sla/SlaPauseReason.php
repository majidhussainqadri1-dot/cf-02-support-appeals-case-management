<?php

declare(strict_types=1);

namespace Sabri\CF02\Sla;

enum SlaPauseReason: string
{
    case AwaitingRequester = 'awaiting_requester';
    case AwaitingNativeOwner = 'awaiting_native_owner';
    case ApprovedIncidentDependency = 'approved_incident_dependency';
}
