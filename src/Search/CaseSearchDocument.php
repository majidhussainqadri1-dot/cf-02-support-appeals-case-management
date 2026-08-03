<?php

declare(strict_types=1);

namespace Sabri\CF02\Search;

use DateTimeImmutable;
use InvalidArgumentException;
use Sabri\CF02\Domain\SupportCaseId;

final class CaseSearchDocument
{
    /** @param list<string> $authorizedActorReferences */
    public function __construct(
        private readonly SupportCaseId $caseId,
        private readonly string $queueKey,
        private readonly string $category,
        private readonly string $priority,
        private readonly string $state,
        private readonly string $locale,
        private readonly string $safeSubject,
        private readonly array $authorizedActorReferences,
        private readonly DateTimeImmutable $updatedAt,
        private readonly int $recordVersion
    ) {
        foreach ([$queueKey, $category, $state] as $token) {
            if (preg_match('/^[a-z][a-z0-9_.-]{1,63}$/', $token) !== 1) {
                throw new InvalidArgumentException('Invalid search projection token.');
            }
        }
        if (!in_array($priority, ['P1', 'P2', 'P3', 'P4'], true)) {
            throw new InvalidArgumentException('Invalid case priority.');
        }
        if (preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $locale) !== 1) {
            throw new InvalidArgumentException('Invalid search locale.');
        }
        if (trim($safeSubject) === '' || strlen($safeSubject) > 180) {
            throw new InvalidArgumentException('Search subject must be bounded and non-empty.');
        }
        if ($recordVersion < 1) {
            throw new InvalidArgumentException('Search projection version must be positive.');
        }
        foreach ($authorizedActorReferences as $actorReference) {
            if (!is_string($actorReference) || trim($actorReference) === '') {
                throw new InvalidArgumentException('Invalid authorized actor reference.');
            }
        }
        if (count($authorizedActorReferences) !== count(array_unique($authorizedActorReferences))) {
            throw new InvalidArgumentException('Duplicate authorized actors are prohibited.');
        }
    }

    public function caseId(): SupportCaseId { return $this->caseId; }
    public function queueKey(): string { return $this->queueKey; }
    public function category(): string { return $this->category; }
    public function priority(): string { return $this->priority; }
    public function state(): string { return $this->state; }
    public function locale(): string { return $this->locale; }
    public function safeSubject(): string { return $this->safeSubject; }
    public function updatedAt(): DateTimeImmutable { return $this->updatedAt; }
    public function recordVersion(): int { return $this->recordVersion; }
    public function authorizedFor(string $actorReference): bool { return in_array($actorReference, $this->authorizedActorReferences, true); }
}
