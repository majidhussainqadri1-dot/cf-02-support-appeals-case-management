<?php

declare(strict_types=1);

namespace Sabri\CF02\Thread;

enum MessageVisibility: string
{
    case Requester = 'requester';
    case Internal = 'internal';
    case Restricted = 'restricted';
}
