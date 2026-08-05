<?php

declare(strict_types=1);

namespace Sabri\CF02\Retention;

use DateTimeImmutable;
use InvalidArgumentException;
use Sabri\CF02\Domain\SupportCaseId;
use Sabri\CF02\Evidence\LegalHold;

final class PurgePlanner
{
    /**
     * @param list<LegalHold> $holds
     * @return array{action:string,eligible_at:?string,preserve:list<string>,reasons:list<string>}
     */
    public function plan(
        SupportCaseId $caseId,
        string $category,
        string $state,
        DateTimeImmutable $stateChangedAt,
        RetentionPolicy $policy,
        array $holds,
        DateTimeImmutable $at
    ): array {
        if ($policy->category() !== $category) {
            throw new InvalidArgumentException('Retention policy category does not match the case.');
        }
        if (!in_array($state, ['open', 'resolved', 'closed', 'deleted'], true)) {
            throw new InvalidArgumentException('Invalid retention case state.');
        }
        foreach ($holds as $hold) {
            if (!$hold instanceof LegalHold) {
                throw new InvalidArgumentException('Retention hold corpus is malformed.');
            }
            if ($hold->appliesTo($caseId, $category)) {
                return [
                    'action' => 'hold',
                    'eligible_at' => null,
                    'preserve' => ['case', 'attachments', 'decision_evidence', 'audit'],
                    'reasons' => ['An active case/category hold suspends destructive retention actions.'],
                ];
            }
        }

        $days = in_array($state, ['open', 'resolved'], true) ? $policy->openCaseDays() : $policy->closedCaseDays();
        $eligibleAt = $stateChangedAt->modify(sprintf('+%d days', $days));
        if ($at < $eligibleAt) {
            return [
                'action' => 'retain',
                'eligible_at' => $eligibleAt->format(DATE_ATOM),
                'preserve' => ['case', 'attachments', 'audit'],
                'reasons' => ['The category-specific retention period has not elapsed.'],
            ];
        }

        return [
            'action' => 'purge',
            'eligible_at' => $eligibleAt->format(DATE_ATOM),
            'preserve' => $policy->preserveDecisionEvidence() ? ['minimal_decision_evidence', 'audit_tombstone'] : ['audit_tombstone'],
            'reasons' => ['Retention period elapsed and no active hold applies. Purge canonical data, derivatives, caches, indexes and provider copies.'],
        ];
    }
}
