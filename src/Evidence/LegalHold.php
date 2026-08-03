<?php

declare(strict_types=1);

namespace Sabri\CF02\Evidence;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Sabri\CF02\Domain\ConcurrencyConflict;
use Sabri\CF02\Domain\SupportCaseId;

final class LegalHold
{
    private int $version = 1;
    private bool $active = true;
    private ?DateTimeImmutable $releasedAt = null;
    private ?string $releaseReason = null;

    public function __construct(
        private readonly string $holdId,
        private readonly ?SupportCaseId $caseId,
        private readonly ?string $category,
        private readonly string $reasonCode,
        private readonly string $authorityReference,
        private readonly DateTimeImmutable $placedAt,
        private DateTimeImmutable $reviewDueAt
    ) {
        if (preg_match('/^CF02-HOLD-[A-F0-9]{20}$/', $holdId) !== 1) {
            throw new InvalidArgumentException('Invalid legal-hold ID.');
        }
        if (($caseId === null) === ($category === null)) {
            throw new InvalidArgumentException('A hold must target exactly one case or category.');
        }
        if ($category !== null && preg_match('/^[a-z][a-z0-9_.-]{1,63}$/', $category) !== 1) {
            throw new InvalidArgumentException('Invalid hold category.');
        }
        if (preg_match('/^[a-z][a-z0-9_.-]{2,63}$/', $reasonCode) !== 1 || trim($authorityReference) === '') {
            throw new InvalidArgumentException('Hold reason and authority are required.');
        }
        if ($reviewDueAt <= $placedAt || $reviewDueAt > $placedAt->modify('+1 year')) {
            throw new InvalidArgumentException('Hold review date must be bounded and after placement.');
        }
    }

    public static function forCase(
        SupportCaseId $caseId,
        string $reasonCode,
        string $authorityReference,
        DateTimeImmutable $placedAt,
        DateTimeImmutable $reviewDueAt
    ): self {
        return new self('CF02-HOLD-' . strtoupper(bin2hex(random_bytes(10))), $caseId, null, $reasonCode, $authorityReference, $placedAt, $reviewDueAt);
    }

    public static function forCategory(
        string $category,
        string $reasonCode,
        string $authorityReference,
        DateTimeImmutable $placedAt,
        DateTimeImmutable $reviewDueAt
    ): self {
        return new self('CF02-HOLD-' . strtoupper(bin2hex(random_bytes(10))), null, $category, $reasonCode, $authorityReference, $placedAt, $reviewDueAt);
    }

    public function review(DateTimeImmutable $newReviewDueAt, DateTimeImmutable $at, int $expectedVersion): void
    {
        $this->assertVersion($expectedVersion);
        if (!$this->active) {
            throw new DomainException('Released hold cannot be reviewed.');
        }
        if ($at < $this->placedAt || $newReviewDueAt <= $at || $newReviewDueAt > $at->modify('+1 year')) {
            throw new InvalidArgumentException('Invalid hold review chronology.');
        }
        $this->reviewDueAt = $newReviewDueAt;
        ++$this->version;
    }

    public function release(string $reason, DateTimeImmutable $at, int $expectedVersion): void
    {
        $this->assertVersion($expectedVersion);
        if (!$this->active) {
            throw new DomainException('Hold is already released.');
        }
        if (trim($reason) === '' || $at < $this->placedAt) {
            throw new InvalidArgumentException('Hold release reason and chronology are required.');
        }
        $this->active = false;
        $this->releasedAt = $at;
        $this->releaseReason = trim($reason);
        ++$this->version;
    }

    public function appliesTo(SupportCaseId $caseId, string $category): bool
    {
        if (!$this->active) {
            return false;
        }
        return ($this->caseId !== null && $this->caseId->equals($caseId))
            || ($this->category !== null && hash_equals($this->category, $category));
    }

    public function isOverdue(DateTimeImmutable $at): bool { return $this->active && $at > $this->reviewDueAt; }
    public function holdId(): string { return $this->holdId; }
    public function active(): bool { return $this->active; }
    public function version(): int { return $this->version; }
    public function reviewDueAt(): DateTimeImmutable { return $this->reviewDueAt; }
    public function releasedAt(): ?DateTimeImmutable { return $this->releasedAt; }
    public function releaseReason(): ?string { return $this->releaseReason; }

    private function assertVersion(int $expectedVersion): void
    {
        if ($expectedVersion !== $this->version) {
            throw new ConcurrencyConflict(sprintf('Stale legal-hold version: expected %d, current %d.', $expectedVersion, $this->version));
        }
    }
}
