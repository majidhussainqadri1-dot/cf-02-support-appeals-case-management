<?php

declare(strict_types=1);

namespace Sabri\CF02\Appeal;

use DateTimeImmutable;
use InvalidArgumentException;

final class AppealEligibilityPolicy
{
    /** @var list<string> */
    private const ALLOWED_GROUNDS = [
        'policy_misapplied',
        'new_evidence',
        'procedural_error',
        'identity_mistake',
        'proportionality',
        'accessibility_barrier',
        'guardian_or_representative',
    ];

    /**
     * @param list<string> $grounds
     */
    public function decide(
        string $originalDecisionId,
        string $appellantReference,
        bool $hasStanding,
        DateTimeImmutable $decisionAt,
        DateTimeImmutable $submittedAt,
        int $deadlineDays,
        array $grounds,
        bool $hasNewEvidence,
        bool $exceptionRequested,
        ?string $exceptionReason,
        bool $retaliationSignal = false
    ): AppealEligibilityDecision {
        if (trim($originalDecisionId) === '' || trim($appellantReference) === '') {
            throw new InvalidArgumentException('Appeal requires original decision and appellant references.');
        }
        if ($submittedAt < $decisionAt || $deadlineDays < 1 || $deadlineDays > 365) {
            throw new InvalidArgumentException('Appeal deadline chronology is invalid.');
        }
        if ($grounds === []) {
            return new AppealEligibilityDecision(false, false, ['No recognized appeal ground was submitted.'], 'Submit a new appeal with at least one recognized ground if the deadline remains open.');
        }
        foreach ($grounds as $ground) {
            if (!is_string($ground) || !in_array($ground, self::ALLOWED_GROUNDS, true)) {
                throw new InvalidArgumentException('Unknown appeal ground.');
            }
        }
        if (count($grounds) !== count(array_unique($grounds))) {
            throw new InvalidArgumentException('Duplicate appeal grounds are prohibited.');
        }
        if (!$hasStanding) {
            return new AppealEligibilityDecision(false, false, ['Appellant standing or verified representation was not established.'], 'Provide verified authority or use the native-owner correction process.');
        }

        $deadlineAt = $decisionAt->modify(sprintf('+%d days', $deadlineDays));
        $late = $submittedAt > $deadlineAt;
        $exceptionApplied = false;
        $reasons = [];

        if ($late) {
            if (!$exceptionRequested || trim((string) $exceptionReason) === '') {
                return new AppealEligibilityDecision(false, false, ['The ordinary appeal deadline expired and no exception basis was supplied.'], 'Request a reasoned deadline exception or use the published further-review path.');
            }
            if (!in_array('accessibility_barrier', $grounds, true)
                && !in_array('guardian_or_representative', $grounds, true)
                && !$hasNewEvidence) {
                return new AppealEligibilityDecision(false, false, ['The supplied exception basis does not meet the governed late-appeal criteria.'], 'Use the published further-review or complaint route.');
            }
            $exceptionApplied = true;
            $reasons[] = 'A documented late-appeal exception was accepted.';
        }

        if (in_array('new_evidence', $grounds, true) && !$hasNewEvidence) {
            return new AppealEligibilityDecision(false, $exceptionApplied, ['New evidence was claimed but no evidence reference was supplied.'], 'Provide the new evidence reference while the submission window remains open.');
        }
        if ($retaliationSignal) {
            $reasons[] = 'A possible retaliation signal requires independent safeguarding review and cannot reduce eligibility.';
        }
        $reasons[] = 'Standing, timing, grounds and evidence requirements were satisfied.';

        return new AppealEligibilityDecision(true, $exceptionApplied, $reasons, null);
    }
}
