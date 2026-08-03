<?php

declare(strict_types=1);

namespace Sabri\CF02\Quality;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Sabri\CF02\Domain\SupportCaseId;

final class QualityReview
{
    /** @var array<string, int> */
    private array $scores;
    /** @var list<string> */
    private array $findings;
    private bool $appealed = false;
    private ?string $correctionReference = null;

    /** @param array<string, int> $scores @param list<string> $findings */
    public function __construct(
        private readonly string $reviewId,
        private readonly SupportCaseId $caseId,
        private readonly string $reviewerReference,
        private readonly string $sampleBasis,
        array $scores,
        array $findings,
        private readonly DateTimeImmutable $reviewedAt,
        private readonly bool $identitySuppressed
    ) {
        if (preg_match('/^CF02-QA-[A-F0-9]{20}$/', $reviewId) !== 1 || trim($reviewerReference) === '') {
            throw new InvalidArgumentException('Invalid quality-review identity.');
        }
        if (!in_array($sampleBasis, ['random', 'risk', 'breach', 'reopen', 'complaint'], true)) {
            throw new InvalidArgumentException('Invalid quality sample basis.');
        }
        $required = ['accuracy', 'empathy', 'compliance', 'security', 'accessibility'];
        $actual = array_keys($scores);
        sort($required);
        sort($actual);
        if ($actual !== $required) {
            throw new InvalidArgumentException('Quality rubric fields are incomplete or contain unsupported fields.');
        }
        foreach ($scores as $score) {
            if (!is_int($score) || $score < 0 || $score > 100) {
                throw new InvalidArgumentException('Quality scores must be integer values between 0 and 100.');
            }
        }
        foreach ($findings as $finding) {
            if (!is_string($finding) || trim($finding) === '') {
                throw new InvalidArgumentException('Quality findings must be non-empty.');
            }
        }
        ksort($scores);
        $this->scores = $scores;
        $this->findings = array_values($findings);
    }

    /** @param array<string, int> $scores @param list<string> $findings */
    public static function create(
        SupportCaseId $caseId,
        string $reviewerReference,
        string $sampleBasis,
        array $scores,
        array $findings,
        DateTimeImmutable $reviewedAt,
        bool $identitySuppressed
    ): self {
        return new self('CF02-QA-' . strtoupper(bin2hex(random_bytes(10))), $caseId, $reviewerReference, $sampleBasis, $scores, $findings, $reviewedAt, $identitySuppressed);
    }

    public function recordAgentAppeal(string $reason): void
    {
        if ($this->appealed) {
            throw new DomainException('Quality review is already appealed.');
        }
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Quality-review appeal reason is required.');
        }
        $this->appealed = true;
        $this->findings[] = 'Agent correction appeal recorded: ' . trim($reason);
    }

    public function recordCorrection(string $reference): void
    {
        if (trim($reference) === '') {
            throw new InvalidArgumentException('Case correction reference is required.');
        }
        $this->correctionReference = trim($reference);
    }

    public function overallScore(): float { return array_sum($this->scores) / count($this->scores); }
    public function requiresCorrection(): bool { return $this->overallScore() < 80.0 || $this->scores['security'] < 90 || $this->scores['compliance'] < 90; }
    public function identitySuppressed(): bool { return $this->identitySuppressed; }
    public function appealed(): bool { return $this->appealed; }
    public function correctionReference(): ?string { return $this->correctionReference; }
    /** @return array<string, int> */ public function scores(): array { return $this->scores; }
    /** @return list<string> */ public function findings(): array { return $this->findings; }
}
