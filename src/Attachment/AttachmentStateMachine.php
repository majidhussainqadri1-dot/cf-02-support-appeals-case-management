<?php

declare(strict_types=1);

namespace Sabri\CF02\Attachment;

use DomainException;

final class AttachmentStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'uploaded' => ['quarantined'],
        'quarantined' => ['scanned', 'rejected'],
        'scanned' => ['available', 'rejected', 'redacted'],
        'available' => ['redacted', 'superseded', 'expired'],
        'redacted' => ['superseded', 'expired'],
        'rejected' => ['purged'],
        'superseded' => ['expired', 'purged'],
        'expired' => ['purged'],
        'purged' => [],
    ];

    public function canTransition(AttachmentState $from, AttachmentState $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    public function assertTransition(AttachmentState $from, AttachmentState $to): void
    {
        if (!$this->canTransition($from, $to)) {
            throw new DomainException(sprintf('Illegal attachment transition: %s -> %s.', $from->value, $to->value));
        }
    }
}
