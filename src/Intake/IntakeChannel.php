<?php

declare(strict_types=1);

namespace Sabri\CF02\Intake;

enum IntakeChannel: string
{
    case Web = 'web';
    case Email = 'email';
    case System = 'system';
}
