<?php

declare(strict_types=1);

namespace Sabri\CF02\Integration;

enum CommandState: string
{
    case Pending = 'pending';
    case Dispatched = 'dispatched';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Compensating = 'compensating';
    case Compensated = 'compensated';
    case DeadLetter = 'dead_letter';
}
