<?php

declare(strict_types=1);

namespace Sabri\CF02\Sla;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Sabri\CF02\Domain\ConcurrencyConflict;

final class SlaClock
{
    private int $version = 1;
    private readonly DateTimeImmutable $firstResponseDeadline;
    private DateTimeImmutable $nextUpdateDeadline;
    private DateTimeImmutable $resolutionDeadline;
    private ?DateTimeImmutable $firstResponseAt = null;
    private ?string $firstResponseEvidence = null;
    private ?DateTimeImmutable $lastUpdateAt = null;
    private ?string $lastUpdateEvidence = null;
    private ?DateTimeImmutable $resolvedAt = null;
    private ?string $resolutionEvidence = null;
    private ?SlaPauseReason $pauseReason = null;
    private ?DateTimeImmutable $pauseStartedAt = null;
    private ?string $pauseEvidenceReference = null;
    private DateTimeImmutable $lastMutationAt;
    /** @var array<string, true> */
    private array $usedEvidence = [];
    /** @var list<array<string, string|int>> */
    private array $pauseHistory = [];

    public function __construct(
        private readonly SlaPolicy $policy,
        private readonly CoverageCalendar $calendar,
        private readonly DateTimeImmutable $startedAt
    ) {
        if ($policy->calendarReference() !== $calendar->reference()) {
            throw new InvalidArgumentException('SLA policy and coverage calendar references do not match.');
        }
        $this->firstResponseDeadline = $calendar->addWorkingMinutes($startedAt, $policy->firstResponseMinutes());
        $this->nextUpdateDeadline = $calendar->addWorkingMinutes($startedAt, $policy->updateMinutes());
        $this->resolutionDeadline = $calendar->addWorkingMinutes($startedAt, $policy->resolutionMinutes());
        $this->lastMutationAt = $startedAt;
    }

    public function recordFirstResponse(DateTimeImmutable $at, string $evidenceReference, int $expectedVersion): bool
    {
        $this->assertVersion($expectedVersion);
        $this->assertMutableAt($at);
        $this->assertNotPaused();
        self::assertPrefixedEvidence($evidenceReference, 'message:');
        if ($this->resolvedAt !== null) {
            throw new DomainException('Resolved SLA clock cannot receive a first response.');
        }
        if ($this->firstResponseAt !== null) {
            if ($this->firstResponseAt == $at && hash_equals((string) $this->firstResponseEvidence, trim($evidenceReference))) {
                return false;
            }
            throw new DomainException('First-response evidence is immutable once recorded.');
        }
        $this->reserveEvidence($evidenceReference);

        $this->firstResponseAt = $at;
        $this->firstResponseEvidence = trim($evidenceReference);
        $this->nextUpdateDeadline = $this->calendar->addWorkingMinutes($at, $this->policy->updateMinutes());
        $this->lastMutationAt = $at;
        ++$this->version;
        return true;
    }

    public function recordUpdate(DateTimeImmutable $at, string $evidenceReference, int $expectedVersion): bool
    {
        $this->assertVersion($expectedVersion);
        $this->assertMutableAt($at);
        $this->assertNotPaused();
        self::assertPrefixedEvidence($evidenceReference, 'message:');
        if ($this->firstResponseAt === null || $this->resolvedAt !== null) {
            throw new DomainException('Update requires an active clock with a recorded first response.');
        }
        if ($this->lastUpdateAt !== null && $this->lastUpdateAt == $at && hash_equals((string) $this->lastUpdateEvidence, trim($evidenceReference))) {
            return false;
        }
        $this->reserveEvidence($evidenceReference);

        $this->lastUpdateAt = $at;
        $this->lastUpdateEvidence = trim($evidenceReference);
        $this->nextUpdateDeadline = $this->calendar->addWorkingMinutes($at, $this->policy->updateMinutes());
        $this->lastMutationAt = $at;
        ++$this->version;
        return true;
    }

    public function pause(
        SlaPauseReason $reason,
        string $evidenceReference,
        DateTimeImmutable $at,
        int $expectedVersion
    ): void {
        $this->assertVersion($expectedVersion);
        $this->assertMutableAt($at);
        if ($this->resolvedAt !== null || $this->pauseReason !== null) {
            throw new DomainException('SLA clock is resolved or already paused.');
        }
        if ($this->firstResponseAt === null) {
            throw new DomainException('SLA pause is prohibited before the first response is recorded.');
        }
        if (str_starts_with($this->status($at), 'breached_')) {
            throw new DomainException('A breached SLA cannot be hidden by starting a pause.');
        }
        if (!$this->policy->allowsPause($reason)) {
            throw new DomainException('SLA policy does not allow this pause reason.');
        }
        self::assertEvidenceReference($reason, $evidenceReference);
        $this->reserveEvidence($evidenceReference);

        $this->pauseReason = $reason;
        $this->pauseStartedAt = $at;
        $this->pauseEvidenceReference = trim($evidenceReference);
        $this->lastMutationAt = $at;
        ++$this->version;
    }

    public function resume(DateTimeImmutable $at, int $expectedVersion): int
    {
        $this->assertVersion($expectedVersion);
        $this->assertMutableAt($at);
        if ($this->pauseReason === null || $this->pauseStartedAt === null || $this->pauseEvidenceReference === null) {
            throw new DomainException('SLA clock is not paused.');
        }

        $pausedWorkingMinutes = $this->calendar->workingMinutesBetween($this->pauseStartedAt, $at);
        $this->nextUpdateDeadline = $this->calendar->addWorkingMinutes($this->nextUpdateDeadline, $pausedWorkingMinutes);
        $this->resolutionDeadline = $this->calendar->addWorkingMinutes($this->resolutionDeadline, $pausedWorkingMinutes);

        $this->pauseHistory[] = [
            'reason' => $this->pauseReason->value,
            'evidence_reference' => $this->pauseEvidenceReference,
            'started_at' => $this->pauseStartedAt->format(DATE_ATOM),
            'resumed_at' => $at->format(DATE_ATOM),
            'working_minutes' => $pausedWorkingMinutes,
        ];

        $this->pauseReason = null;
        $this->pauseStartedAt = null;
        $this->pauseEvidenceReference = null;
        $this->lastMutationAt = $at;
        ++$this->version;
        return $pausedWorkingMinutes;
    }

    public function resolve(DateTimeImmutable $at, string $evidenceReference, int $expectedVersion): void
    {
        $this->assertVersion($expectedVersion);
        $this->assertMutableAt($at);
        $this->assertNotPaused();
        self::assertPrefixedEvidence($evidenceReference, 'resolution:');
        if ($this->resolvedAt !== null) {
            throw new DomainException('SLA clock is already resolved.');
        }
        if ($this->firstResponseAt === null) {
            throw new DomainException('SLA resolution requires a recorded first response.');
        }
        $this->reserveEvidence($evidenceReference);
        $this->resolvedAt = $at;
        $this->resolutionEvidence = trim($evidenceReference);
        $this->lastMutationAt = $at;
        ++$this->version;
    }

    public function status(DateTimeImmutable $at): string
    {
        if ($this->resolvedAt !== null) {
            return 'completed';
        }
        if ($this->pauseReason !== null) {
            return 'paused';
        }
        if ($at > $this->resolutionDeadline) {
            return 'breached_resolution';
        }
        if ($this->firstResponseAt === null && $at > $this->firstResponseDeadline) {
            return 'breached_first_response';
        }
        if ($this->firstResponseAt !== null && $at > $this->nextUpdateDeadline) {
            return 'breached_update';
        }

        $remaining = $this->remainingResolutionWorkingMinutes($at);
        $warningAt = (int) ceil($this->policy->resolutionMinutes() * (100 - $this->policy->warningThresholdPercent()) / 100);
        return $remaining <= $warningAt ? 'at_risk' : 'on_track';
    }

    public function remainingResolutionWorkingMinutes(DateTimeImmutable $at): int
    {
        if ($at >= $this->resolutionDeadline) {
            return 0;
        }
        return $this->calendar->workingMinutesBetween($at, $this->resolutionDeadline);
    }

    /** @return array<string, mixed> */
    public function snapshot(DateTimeImmutable $at): array
    {
        return [
            'policy_id' => $this->policy->policyId(),
            'policy_version' => $this->policy->version(),
            'status' => $this->status($at),
            'version' => $this->version,
            'started_at' => $this->startedAt->format(DATE_ATOM),
            'first_response_deadline' => $this->firstResponseDeadline->format(DATE_ATOM),
            'next_update_deadline' => $this->nextUpdateDeadline->format(DATE_ATOM),
            'resolution_deadline' => $this->resolutionDeadline->format(DATE_ATOM),
            'remaining_resolution_working_minutes' => $this->remainingResolutionWorkingMinutes($at),
            'pause_reason' => $this->pauseReason?->value,
            'first_response_evidence' => $this->firstResponseEvidence,
            'last_update_evidence' => $this->lastUpdateEvidence,
            'resolution_evidence' => $this->resolutionEvidence,
        ];
    }

    public function version(): int { return $this->version; }
    public function policy(): SlaPolicy { return $this->policy; }
    public function firstResponseDeadline(): DateTimeImmutable { return $this->firstResponseDeadline; }
    public function nextUpdateDeadline(): DateTimeImmutable { return $this->nextUpdateDeadline; }
    public function resolutionDeadline(): DateTimeImmutable { return $this->resolutionDeadline; }
    public function firstResponseAt(): ?DateTimeImmutable { return $this->firstResponseAt; }
    public function resolvedAt(): ?DateTimeImmutable { return $this->resolvedAt; }
    public function lastMutationAt(): DateTimeImmutable { return $this->lastMutationAt; }
    public function isPaused(): bool { return $this->pauseReason !== null; }
    /** @return list<array<string, string|int>> */ public function pauseHistory(): array { return $this->pauseHistory; }

    private function assertNotPaused(): void
    {
        if ($this->pauseReason !== null) {
            throw new DomainException('Paused SLA clock must be resumed before this action.');
        }
    }

    private function assertMutableAt(DateTimeImmutable $at): void
    {
        if ($at < $this->startedAt || $at < $this->lastMutationAt) {
            throw new DomainException('SLA mutation timestamp is backdated.');
        }
    }

    private function assertVersion(int $expectedVersion): void
    {
        if ($expectedVersion !== $this->version) {
            throw new ConcurrencyConflict(sprintf('Stale SLA version: expected %d, current %d.', $expectedVersion, $this->version));
        }
    }

    private function reserveEvidence(string $reference): void
    {
        $reference = trim($reference);
        if (isset($this->usedEvidence[$reference])) {
            throw new DomainException('SLA evidence reference has already been consumed by another clock mutation.');
        }
        $this->usedEvidence[$reference] = true;
    }

    private static function assertPrefixedEvidence(string $reference, string $prefix): void
    {
        $reference = trim($reference);
        if (!str_starts_with($reference, $prefix) || strlen($reference) <= strlen($prefix)) {
            throw new InvalidArgumentException('SLA evidence reference has an invalid type or identifier.');
        }
    }

    private static function assertEvidenceReference(SlaPauseReason $reason, string $reference): void
    {
        $prefix = match ($reason) {
            SlaPauseReason::AwaitingRequester => 'message:',
            SlaPauseReason::AwaitingNativeOwner => 'command:',
            SlaPauseReason::ApprovedIncidentDependency => 'incident:',
        };
        self::assertPrefixedEvidence($reference, $prefix);
    }
}
