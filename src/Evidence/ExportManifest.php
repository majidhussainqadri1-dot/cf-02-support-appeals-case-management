<?php

declare(strict_types=1);

namespace Sabri\CF02\Evidence;

use DateTimeImmutable;
use InvalidArgumentException;
use Sabri\CF02\Domain\SupportCaseId;

final class ExportManifest
{
    /** @param array<string, string> $files */
    public function __construct(
        private readonly string $exportId,
        private readonly SupportCaseId $caseId,
        private readonly string $requesterReference,
        private readonly string $purpose,
        private readonly array $files,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $expiresAt,
        private readonly string $manifestHash
    ) {
        if (preg_match('/^CF02-EXP-[A-F0-9]{20}$/', $exportId) !== 1) {
            throw new InvalidArgumentException('Invalid export ID.');
        }
        if (trim($requesterReference) === '' || preg_match('/^[a-z][a-z0-9_.-]{2,63}$/', $purpose) !== 1) {
            throw new InvalidArgumentException('Export requester and purpose are required.');
        }
        if ($files === [] || count($files) > 100) {
            throw new InvalidArgumentException('Export manifest must contain a bounded file set.');
        }
        foreach ($files as $name => $hash) {
            if (preg_match('/^[A-Za-z0-9_.-]{1,120}$/', $name) !== 1 || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
                throw new InvalidArgumentException('Invalid export file entry.');
            }
        }
        if ($expiresAt <= $createdAt || $expiresAt > $createdAt->modify('+7 days')) {
            throw new InvalidArgumentException('Export expiry must be time-limited to seven days or less.');
        }
        if (preg_match('/^[a-f0-9]{64}$/', $manifestHash) !== 1) {
            throw new InvalidArgumentException('Invalid export manifest hash.');
        }
    }

    /** @param array<string, string> $files */
    public static function create(
        SupportCaseId $caseId,
        string $requesterReference,
        string $purpose,
        array $files,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt
    ): self {
        ksort($files);
        $payload = json_encode([
            'case_id' => $caseId->value(),
            'requester' => $requesterReference,
            'purpose' => $purpose,
            'files' => $files,
            'created_at' => $createdAt->format(DATE_ATOM),
            'expires_at' => $expiresAt->format(DATE_ATOM),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        return new self(
            'CF02-EXP-' . strtoupper(bin2hex(random_bytes(10))),
            $caseId,
            $requesterReference,
            $purpose,
            $files,
            $createdAt,
            $expiresAt,
            hash('sha256', $payload)
        );
    }

    public function exportId(): string { return $this->exportId; }
    public function caseId(): SupportCaseId { return $this->caseId; }
    public function requesterReference(): string { return $this->requesterReference; }
    public function purpose(): string { return $this->purpose; }
    /** @return array<string, string> */ public function files(): array { return $this->files; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function expiresAt(): DateTimeImmutable { return $this->expiresAt; }
    public function manifestHash(): string { return $this->manifestHash; }
    public function isExpired(DateTimeImmutable $at): bool { return $at >= $this->expiresAt; }
}
