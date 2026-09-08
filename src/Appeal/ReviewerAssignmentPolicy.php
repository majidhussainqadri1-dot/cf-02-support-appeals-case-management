<?php

declare(strict_types=1);

namespace Sabri\CF02\Appeal;

use InvalidArgumentException;

final class ReviewerAssignmentPolicy
{
    /**
     * @param list<ReviewerProfile> $reviewers
     * @return array{reviewer_reference:?string,reasons:list<string>,eligible_count:int}
     */
    public function assign(
        string $originalDecisionId,
        string $originalDecisionActor,
        string $requiredCompetence,
        bool $sensitive,
        array $reviewers,
        ?string $originalDecisionUnit = null
    ): array {
        if (trim($originalDecisionId) === '' || trim($originalDecisionActor) === '' || trim($requiredCompetence) === '') {
            throw new InvalidArgumentException('Reviewer assignment requires original decision, actor and competence.');
        }
        if ($originalDecisionUnit !== null && preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:@-]{1,63}$/', $originalDecisionUnit) !== 1) {
            throw new InvalidArgumentException('Original decision organization unit is invalid.');
        }
        $eligible = [];
        foreach ($reviewers as $reviewer) {
            if (!$reviewer instanceof ReviewerProfile) {
                throw new InvalidArgumentException('Reviewer candidate list is malformed.');
            }
            if (!$reviewer->available()
                || !$reviewer->hasCompetence($requiredCompetence)
                || $reviewer->wasInvolved($originalDecisionId)
                || $reviewer->conflictsWith($originalDecisionActor)
                || ($originalDecisionUnit !== null && hash_equals($reviewer->organizationUnit(), $originalDecisionUnit))
                || ($sensitive && !$reviewer->sensitiveClearance())) {
                continue;
            }
            $eligible[] = $reviewer;
        }
        usort($eligible, static fn (ReviewerProfile $a, ReviewerProfile $b): int => strcmp($a->reviewerReference(), $b->reviewerReference()));
        if ($eligible === []) {
            return [
                'reviewer_reference' => null,
                'reasons' => ['No available reviewer satisfies competence, prior-involvement, conflict, organizational-separation and clearance requirements.'],
                'eligible_count' => 0,
            ];
        }
        return [
            'reviewer_reference' => $eligible[0]->reviewerReference(),
            'reasons' => ['Selected reviewer is organizationally independent, competent, available, conflict-free and appropriately cleared.'],
            'eligible_count' => count($eligible),
        ];
    }
}
