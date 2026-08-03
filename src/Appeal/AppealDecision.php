<?php

declare(strict_types=1);

namespace Sabri\CF02\Appeal;

use DateTimeImmutable;
use InvalidArgumentException;

final class AppealDecision
{
    /** @param list<string> $findings @param list<string> $evidenceConsidered @param list<string> $effectiveActions @param list<string> $furtherRights */
    public function __construct(
        private readonly string $outcome,
        private readonly string $policyVersion,
        private readonly array $findings,
        private readonly array $evidenceConsidered,
        private readonly array $effectiveActions,
        private readonly array $furtherRights,
        private readonly string $reviewerReference,
        private readonly DateTimeImmutable $decidedAt,
        private readonly string $decisionHash
    ) {
        if (!in_array($outcome, ['uphold', 'modify', 'overturn', 'remand', 'withdraw'], true)) {
            throw new InvalidArgumentException('Invalid appeal outcome.');
        }
        if (trim($policyVersion) === '' || trim($reviewerReference) === '') {
            throw new InvalidArgumentException('Appeal decision policy and reviewer are required.');
        }
        foreach ([$findings, $evidenceConsidered, $furtherRights] as $requiredSet) {
            if ($requiredSet === []) {
                throw new InvalidArgumentException('Appeal decision requires findings, evidence and further-rights statements.');
            }
            foreach ($requiredSet as $item) {
                if (!is_string($item) || trim($item) === '') {
                    throw new InvalidArgumentException('Appeal decision contains an invalid statement.');
                }
            }
        }
        if (in_array($outcome, ['modify', 'overturn', 'remand'], true) && $effectiveActions === []) {
            throw new InvalidArgumentException('Outcome requires at least one effective action.');
        }
        if (preg_match('/^[a-f0-9]{64}$/', $decisionHash) !== 1) {
            throw new InvalidArgumentException('Invalid appeal decision hash.');
        }
    }

    /** @param list<string> $findings @param list<string> $evidenceConsidered @param list<string> $effectiveActions @param list<string> $furtherRights */
    public static function create(
        string $outcome,
        string $policyVersion,
        array $findings,
        array $evidenceConsidered,
        array $effectiveActions,
        array $furtherRights,
        string $reviewerReference,
        DateTimeImmutable $decidedAt
    ): self {
        $payload = [
            'outcome' => $outcome,
            'policy_version' => $policyVersion,
            'findings' => $findings,
            'evidence_considered' => $evidenceConsidered,
            'effective_actions' => $effectiveActions,
            'further_rights' => $furtherRights,
            'reviewer_reference' => $reviewerReference,
            'decided_at' => $decidedAt->format(DATE_ATOM),
        ];
        return new self(
            $outcome,
            $policyVersion,
            $findings,
            $evidenceConsidered,
            $effectiveActions,
            $furtherRights,
            $reviewerReference,
            $decidedAt,
            hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
        );
    }

    public function outcome(): string { return $this->outcome; }
    public function policyVersion(): string { return $this->policyVersion; }
    /** @return list<string> */ public function findings(): array { return $this->findings; }
    /** @return list<string> */ public function evidenceConsidered(): array { return $this->evidenceConsidered; }
    /** @return list<string> */ public function effectiveActions(): array { return $this->effectiveActions; }
    /** @return list<string> */ public function furtherRights(): array { return $this->furtherRights; }
    public function reviewerReference(): string { return $this->reviewerReference; }
    public function decidedAt(): DateTimeImmutable { return $this->decidedAt; }
    public function decisionHash(): string { return $this->decisionHash; }
}
