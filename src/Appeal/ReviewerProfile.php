<?php

declare(strict_types=1);

namespace Sabri\CF02\Appeal;

use InvalidArgumentException;

final class ReviewerProfile
{
    /** @param list<string> $competencies @param list<string> $priorDecisionIds @param list<string> $conflictActors */
    public function __construct(
        private readonly string $reviewerReference,
        private readonly array $competencies,
        private readonly array $priorDecisionIds,
        private readonly array $conflictActors,
        private readonly bool $available,
        private readonly bool $sensitiveClearance,
        private readonly string $organizationUnit = 'independent_review'
    ) {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:@-]{2,127}$/', $reviewerReference) !== 1) {
            throw new InvalidArgumentException('Invalid reviewer reference.');
        }
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:@-]{1,63}$/', $organizationUnit) !== 1) {
            throw new InvalidArgumentException('Invalid reviewer organization unit.');
        }
        foreach ([$competencies, $priorDecisionIds, $conflictActors] as $set) {
            foreach ($set as $value) {
                if (!is_string($value) || trim($value) === '') {
                    throw new InvalidArgumentException('Reviewer profile contains an invalid token.');
                }
            }
            if (count($set) !== count(array_unique($set))) {
                throw new InvalidArgumentException('Reviewer profile contains duplicate values.');
            }
        }
        if ($competencies === []) {
            throw new InvalidArgumentException('Reviewer requires at least one competence.');
        }
    }

    public function reviewerReference(): string { return $this->reviewerReference; }
    public function available(): bool { return $this->available; }
    public function sensitiveClearance(): bool { return $this->sensitiveClearance; }
    public function organizationUnit(): string { return $this->organizationUnit; }
    public function hasCompetence(string $competence): bool { return in_array($competence, $this->competencies, true); }
    public function wasInvolved(string $decisionId): bool { return in_array($decisionId, $this->priorDecisionIds, true); }
    public function conflictsWith(string $actorReference): bool { return in_array($actorReference, $this->conflictActors, true); }
}
