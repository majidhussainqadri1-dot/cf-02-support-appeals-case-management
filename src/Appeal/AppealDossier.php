<?php

declare(strict_types=1);

namespace Sabri\CF02\Appeal;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final class AppealDossier
{
    private int $version = 1;
    /** @var list<array{type:string,reference:string,submitted_by:string,submitted_at:string,hash:string}> */
    private array $submissions = [];
    private readonly string $originalDecisionHash;

    /** @param list<string> $originalEvidenceReferences */
    public function __construct(
        private readonly string $dossierId,
        private readonly string $originalDecisionId,
        private readonly string $originalDecisionReason,
        private readonly string $originalPolicyVersion,
        private readonly array $originalEvidenceReferences,
        private readonly DateTimeImmutable $createdAt
    ) {
        if (preg_match('/^CF02-DOS-[A-F0-9]{20}$/', $dossierId) !== 1) {
            throw new InvalidArgumentException('Invalid appeal dossier ID.');
        }
        if (trim($originalDecisionId) === '' || trim($originalDecisionReason) === '' || trim($originalPolicyVersion) === '') {
            throw new InvalidArgumentException('Appeal dossier requires immutable original decision data.');
        }
        if ($originalEvidenceReferences === []) {
            throw new InvalidArgumentException('Appeal dossier requires original evidence references.');
        }
        foreach ($originalEvidenceReferences as $reference) {
            if (!is_string($reference) || trim($reference) === '') {
                throw new InvalidArgumentException('Invalid original evidence reference.');
            }
        }
        if (count($originalEvidenceReferences) !== count(array_unique($originalEvidenceReferences))) {
            throw new InvalidArgumentException('Duplicate original evidence references are prohibited.');
        }
        $this->originalDecisionHash = hash('sha256', json_encode([
            'decision_id' => $originalDecisionId,
            'reason' => $originalDecisionReason,
            'policy_version' => $originalPolicyVersion,
            'evidence' => $originalEvidenceReferences,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** @param list<string> $originalEvidenceReferences */
    public static function create(
        string $originalDecisionId,
        string $originalDecisionReason,
        string $originalPolicyVersion,
        array $originalEvidenceReferences,
        DateTimeImmutable $createdAt
    ): self {
        return new self(
            'CF02-DOS-' . strtoupper(bin2hex(random_bytes(10))),
            $originalDecisionId,
            $originalDecisionReason,
            $originalPolicyVersion,
            $originalEvidenceReferences,
            $createdAt
        );
    }

    public function addSubmission(
        string $type,
        string $reference,
        string $submittedBy,
        DateTimeImmutable $submittedAt,
        int $expectedVersion
    ): bool {
        if ($expectedVersion !== $this->version) {
            throw new DomainException('Stale appeal dossier version.');
        }
        if (!in_array($type, ['appellant_statement', 'new_evidence', 'native_response', 'reviewer_finding', 'accessibility_adjustment'], true)) {
            throw new InvalidArgumentException('Invalid appeal dossier submission type.');
        }
        if (trim($reference) === '' || trim($submittedBy) === '' || $submittedAt < $this->createdAt) {
            throw new InvalidArgumentException('Invalid appeal dossier submission.');
        }
        foreach ($this->submissions as $submission) {
            if ($submission['type'] === $type && hash_equals($submission['reference'], $reference)) {
                return false;
            }
        }
        $this->submissions[] = [
            'type' => $type,
            'reference' => trim($reference),
            'submitted_by' => trim($submittedBy),
            'submitted_at' => $submittedAt->format(DATE_ATOM),
            'hash' => hash('sha256', $type . "\0" . $reference . "\0" . $submittedBy . "\0" . $submittedAt->format(DATE_ATOM)),
        ];
        ++$this->version;
        return true;
    }

    public function verifyOriginalIntegrity(
        string $decisionId,
        string $reason,
        string $policyVersion,
        array $evidenceReferences
    ): bool {
        $hash = hash('sha256', json_encode([
            'decision_id' => $decisionId,
            'reason' => $reason,
            'policy_version' => $policyVersion,
            'evidence' => $evidenceReferences,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return hash_equals($this->originalDecisionHash, $hash);
    }

    public function dossierHash(): string
    {
        return hash('sha256', json_encode([
            'original_hash' => $this->originalDecisionHash,
            'submissions' => $this->submissions,
            'version' => $this->version,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public function dossierId(): string { return $this->dossierId; }
    public function originalDecisionId(): string { return $this->originalDecisionId; }
    public function originalPolicyVersion(): string { return $this->originalPolicyVersion; }
    public function originalDecisionHash(): string { return $this->originalDecisionHash; }
    public function version(): int { return $this->version; }
    /** @return list<array{type:string,reference:string,submitted_by:string,submitted_at:string,hash:string}> */ public function submissions(): array { return $this->submissions; }
}
