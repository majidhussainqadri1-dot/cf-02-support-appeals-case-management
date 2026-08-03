<?php

declare(strict_types=1);

namespace Sabri\CF02\Incident;

enum IncidentStatus: string
{
    case Open = 'open';
    case Monitoring = 'monitoring';
    case Resolved = 'resolved';
}
