<?php

declare(strict_types=1);

namespace Sabri\CF02\Delivery;

use InvalidArgumentException;

final class OutcomeDeliveryGate
{
    /** @return list<string> */
    public static function validate(string $status, bool $autoCloseRequested): array
    {
        if (!in_array($status, ['queued', 'sending', 'sent', 'failed', 'dead_letter'], true)) {
            throw new InvalidArgumentException('Unknown outcome-notification delivery status.');
        }
        $reasons = [];
        if (in_array($status, ['failed', 'dead_letter'], true)) {
            $reasons[] = 'Outcome notification delivery failed; case must not be represented as finally resolved without explicit recovery/escalation.';
        }
        if ($autoCloseRequested && $status !== 'sent') {
            $reasons[] = 'Automatic closure requires confirmed outcome-notification delivery.';
        }
        return $reasons;
    }
}
