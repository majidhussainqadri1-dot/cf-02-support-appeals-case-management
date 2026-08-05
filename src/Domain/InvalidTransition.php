<?php

declare(strict_types=1);

namespace Sabri\CF02\Domain;

use DomainException;

final class InvalidTransition extends DomainException
{
    public static function between(CaseState $from, CaseState $to): self
    {
        return new self(sprintf('Transition from %s to %s is not permitted.', $from->value, $to->value));
    }
}
