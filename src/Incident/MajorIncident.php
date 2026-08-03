<?php

declare(strict_types=1);

namespace Sabri\CF02\Incident;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Sabri\CF02\Domain\ConcurrencyConflict;
use Sabri\CF02\Domain\SupportCaseId;
use Sabri\CF02\Security\SensitiveContentDetector;

final class MajorIncident
{
    private int $version = 1;
    private IncidentStatus $status = IncidentStatus::Open;
    private ?string $resolutionSummary = null;
    private ?string $resolutionNoticeReference = null;
    private DateTimeImmutable $lastMutationAt;
    /** @var array<string, array{queue_key:string, linked_at:DateTimeImmutable, actor:string}> */
    private array $caseLinks = [];
    /** @var list<array<string, string>> */
    private array $history = [];

    public function __construct(
        private readonly string $incidentId,
        private readonly string $serviceKey,
        private readonly string $publicSummary,
        private DateTimeImmutable $nextUpdateAt,
        ?DateTimeImmutable $openedAt = null
    ) {
        $openedAt ??= new DateTimeImmutable('now');
        if (preg_match('/^CF02-INC-\d{4}$/', $incidentId) !== 1) {
            throw new InvalidArgumentException('Invalid major-incident ID.');
        }
        if (preg_match('/^[a-z][a-z0-9_.-]*$/', $serviceKey) !== 1 || trim($publicSummary) === '') {
            throw new InvalidArgumentException('Major-incident service and public summary are required.');
        }
        if (SensitiveContentDetector::containsProhibitedSecret($publicSummary)) {
            throw new InvalidArgumentException('Public incident summary contains prohibited secret material.');
        }
        if ($nextUpdateAt <= $openedAt) {
            throw new InvalidArgumentException('Major-incident next update must be after opening time.');
        }
        $this->lastMutationAt = $openedAt;
        $this->history[] = ['type' => 'opened', 'at' => $openedAt->format(DATE_ATOM)];
    }

    public function linkCase(
        SupportCaseId $caseId,
        string $serviceKey,
        string $queueKey,
        string $actorReference,
        DateTimeImmutable $at,
        int $expectedVersion
    ): bool {
        $this->assertVersion($expectedVersion);
        $this->assertChronological($at);
        if ($this->status === IncidentStatus::Resolved) {
            throw new DomainException('Resolved incident cannot accept new case links.');
        }
        if (!hash_equals($this->serviceKey, $serviceKey)) {
            throw new DomainException('Case service signature does not match the incident.');
        }
        if (preg_match('/^[a-z][a-z0-9_]*$/', $queueKey) !== 1 || trim($actorReference) === '') {
            throw new InvalidArgumentException('Incident case link requires queue and actor.');
        }

        $key = $caseId->value();
        $existing = $this->caseLinks[$key] ?? null;
        if ($existing !== null) {
            if ($existing['queue_key'] !== $queueKey) {
                throw new DomainException('Existing incident link cannot be rebound to another queue.');
            }
            return false;
        }

        $this->caseLinks[$key] = ['queue_key' => $queueKey, 'linked_at' => $at, 'actor' => $actorReference];
        $this->history[] = [
            'type' => 'case_linked',
            'case_id' => $key,
            'queue_key' => $queueKey,
            'actor' => $actorReference,
            'at' => $at->format(DATE_ATOM),
        ];
        $this->lastMutationAt = $at;
        ++$this->version;
        return true;
    }

    public function unlinkCase(
        SupportCaseId $caseId,
        string $reason,
        string $actorReference,
        DateTimeImmutable $at,
        int $expectedVersion
    ): bool {
        $this->assertVersion($expectedVersion);
        $this->assertChronological($at);
        if (trim($reason) === '' || trim($actorReference) === '') {
            throw new InvalidArgumentException('Incident unlink reason and actor are required.');
        }
        $key = $caseId->value();
        if (!isset($this->caseLinks[$key])) {
            return false;
        }

        unset($this->caseLinks[$key]);
        $this->history[] = [
            'type' => 'case_unlinked',
            'case_id' => $key,
            'reason' => trim($reason),
            'actor' => $actorReference,
            'at' => $at->format(DATE_ATOM),
        ];
        $this->lastMutationAt = $at;
        ++$this->version;
        return true;
    }

    public function markMonitoring(
        DateTimeImmutable $nextUpdateAt,
        string $actorReference,
        DateTimeImmutable $at,
        int $expectedVersion
    ): void {
        $this->assertVersion($expectedVersion);
        $this->assertChronological($at);
        if ($this->status !== IncidentStatus::Open || trim($actorReference) === '') {
            throw new DomainException('Only an open incident may enter monitoring.');
        }
        if ($nextUpdateAt <= $at) {
            throw new InvalidArgumentException('Monitoring next-update time must be in the future.');
        }
        $this->status = IncidentStatus::Monitoring;
        $this->nextUpdateAt = $nextUpdateAt;
        $this->history[] = ['type' => 'monitoring', 'actor' => $actorReference, 'at' => $at->format(DATE_ATOM)];
        $this->lastMutationAt = $at;
        ++$this->version;
    }

    public function resolve(
        string $publicResolutionSummary,
        string $noticeReference,
        string $actorReference,
        DateTimeImmutable $at,
        int $expectedVersion
    ): void {
        $this->assertVersion($expectedVersion);
        $this->assertChronological($at);
        if ($this->status === IncidentStatus::Resolved) {
            throw new DomainException('Incident is already resolved.');
        }
        foreach ([$publicResolutionSummary, $noticeReference, $actorReference] as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('Incident resolution summary, notice and actor are required.');
            }
        }
        if (SensitiveContentDetector::containsProhibitedSecret($publicResolutionSummary)) {
            throw new InvalidArgumentException('Public incident resolution contains prohibited secret material.');
        }
        $this->status = IncidentStatus::Resolved;
        $this->resolutionSummary = trim($publicResolutionSummary);
        $this->resolutionNoticeReference = trim($noticeReference);
        $this->history[] = ['type' => 'resolved', 'actor' => $actorReference, 'notice' => $noticeReference, 'at' => $at->format(DATE_ATOM)];
        $this->lastMutationAt = $at;
        ++$this->version;
    }

    /** @return array<string, mixed>|null */
    public function projectionForCase(SupportCaseId $caseId): ?array
    {
        if (!isset($this->caseLinks[$caseId->value()])) {
            return null;
        }
        return [
            'incident_id' => $this->incidentId,
            'status' => $this->status->value,
            'public_summary' => $this->publicSummary,
            'next_update_at' => $this->nextUpdateAt->format(DATE_ATOM),
            'resolution_summary' => $this->resolutionSummary,
            'resolution_notice_available' => $this->resolutionNoticeReference !== null,
            'case_action_required' => 'Continue individual case handling; incident linkage does not merge, close or authorize the case.',
        ];
    }

    public function incidentId(): string { return $this->incidentId; }
    public function serviceKey(): string { return $this->serviceKey; }
    public function status(): IncidentStatus { return $this->status; }
    public function version(): int { return $this->version; }
    public function linkedCaseCount(): int { return count($this->caseLinks); }
    /** @return list<array<string, string>> */ public function history(): array { return $this->history; }

    private function assertChronological(DateTimeImmutable $at): void
    {
        if ($at < $this->lastMutationAt) {
            throw new DomainException('Incident mutation timestamp is backdated.');
        }
    }

    private function assertVersion(int $expectedVersion): void
    {
        if ($expectedVersion !== $this->version) {
            throw new ConcurrencyConflict(sprintf('Stale incident version: expected %d, current %d.', $expectedVersion, $this->version));
        }
    }
}
