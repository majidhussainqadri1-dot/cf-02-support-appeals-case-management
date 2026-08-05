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
        array $reviewers
    ): array {
        if (trim($originalDecisionId) === '' || trim($originalDecisionActor) === '' || trim($requiredCompetence) === '') {
            throw new InvalidArgumentException('Reviewer assignment requires original decision, actor and competence.');
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
                || ($sensitive && !$reviewer->sensitiveClearance())) {
                continue;
            }
            $eligible[] = $reviewer;
        }
        usort($eligible, static fn (ReviewerProfile $a, ReviewerProfile $b): int => strcmp($a->reviewerReference(), $b->reviewerReference()));
        if ($eligible === []) {
            return [
                'reviewer_reference' => null,
                'reasons' => ['No available independent reviewer satisfies competence, conflict and clearance requirements.'],
                'eligible_count' => 0,
            ];
        }
        return [
            'reviewer_reference' => $eligible[0]->reviewerReference(),
            'reasons' => ['Selected reviewer is independent, competent, available and appropriately cleared.'],
            'eligible_count' => count($eligible),
        ];
    }
}
