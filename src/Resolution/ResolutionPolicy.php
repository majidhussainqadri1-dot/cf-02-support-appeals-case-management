<?php

declare(strict_types=1);

namespace Sabri\CF02\Resolution;

use DateTimeImmutable;
use Sabri\CF02\Delivery\OutcomeDeliveryGate;
use Sabri\CF02\Domain\CaseState;

final class ResolutionPolicy
{
    /** @param list<string> $openBlockers @param list<string> $failedNativeCommands @return list<string> */
    public function validate(
        CaseState $currentState,
        ResolutionDecision $decision,
        array $openBlockers,
        array $failedNativeCommands,
        ?DateTimeImmutable $now = null,
        string $outcomeDeliveryStatus = 'sent'
    ): array {
        $now ??= new DateTimeImmutable('now');
        $reasons = [];

        if (!in_array($currentState, [CaseState::InProgress, CaseState::WaitingForUser, CaseState::WaitingForProvider], true)) {
            $reasons[] = 'Case state is not eligible for resolution.';
        }
        if ($openBlockers !== []) {
            $reasons[] = 'Case has open blockers.';
        }
        if ($failedNativeCommands !== []) {
            $reasons[] = 'A required native-owner command failed or remains unreconciled.';
        }
        if (!$decision->outcomeVerified()) {
            $reasons[] = 'Resolution outcome has not been verified.';
        }
        if ($decision->reopenUntil() <= $now) {
            $reasons[] = 'Reopen window must extend into the future.';
        }
        if ($decision->autoCloseEligible() && !$decision->closureNoticeSent()) {
            $reasons[] = 'Automatic closure requires prior user notice.';
        }
        $reasons = array_merge($reasons, OutcomeDeliveryGate::validate($outcomeDeliveryStatus, $decision->autoCloseEligible()));
        return array_values(array_unique($reasons));
    }
}
