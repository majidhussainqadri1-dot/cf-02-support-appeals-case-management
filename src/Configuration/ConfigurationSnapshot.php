<?php

declare(strict_types=1);

namespace Sabri\CF02\Configuration;

use DateTimeImmutable;
use InvalidArgumentException;

final class ConfigurationSnapshot
{
    /** @param array<string, mixed> $configuration @param list<string> $approvers */
    public function __construct(
        private readonly string $configurationId,
        private readonly int $version,
        private readonly string $status,
        private readonly array $configuration,
        private readonly array $approvers,
        private readonly string $createdBy,
        private readonly DateTimeImmutable $createdAt,
        private readonly string $checksum
    ) {
        if (preg_match('/^CF02-CFG-[A-Z0-9-]{3,40}$/', $configurationId) !== 1 || $version < 1) {
            throw new InvalidArgumentException('Invalid configuration identity.');
        }
        if (!in_array($status, ['draft', 'staged', 'active', 'retired', 'rolled_back'], true)) {
            throw new InvalidArgumentException('Invalid configuration status.');
        }
        if ($configuration === [] || trim($createdBy) === '') {
            throw new InvalidArgumentException('Configuration payload and creator are required.');
        }
        foreach ($approvers as $approver) {
            if (!is_string($approver) || trim($approver) === '') {
                throw new InvalidArgumentException('Invalid configuration approver.');
            }
        }
        if (count($approvers) !== count(array_unique($approvers))) {
            throw new InvalidArgumentException('Duplicate configuration approvers are prohibited.');
        }
        if (preg_match('/^[a-f0-9]{64}$/', $checksum) !== 1) {
            throw new InvalidArgumentException('Invalid configuration checksum.');
        }
    }

    /** @param array<string, mixed> $configuration @param list<string> $approvers */
    public static function create(
        string $configurationId,
        int $version,
        string $status,
        array $configuration,
        array $approvers,
        string $createdBy,
        DateTimeImmutable $createdAt
    ): self {
        self::assertSafeConfiguration($configuration);
        $canonical = self::canonicalize($configuration);
        $checksum = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return new self($configurationId, $version, $status, $canonical, $approvers, $createdBy, $createdAt, $checksum);
    }

    public function configurationId(): string { return $this->configurationId; }
    public function version(): int { return $this->version; }
    public function status(): string { return $this->status; }
    /** @return array<string, mixed> */ public function configuration(): array { return $this->configuration; }
    /** @return list<string> */ public function approvers(): array { return $this->approvers; }
    public function createdBy(): string { return $this->createdBy; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function checksum(): string { return $this->checksum; }

    /** @param array<string, mixed> $configuration */
    private static function assertSafeConfiguration(array $configuration): void
    {
        $allowedTopLevel = ['categories', 'forms', 'slas', 'queues', 'skills', 'templates', 'escalation', 'feature_flags'];
        foreach ($configuration as $key => $value) {
            if (!is_string($key) || !in_array($key, $allowedTopLevel, true) || !is_array($value)) {
                throw new InvalidArgumentException('Configuration contains an unsupported section.');
            }
        }
        $encoded = json_encode($configuration, JSON_THROW_ON_ERROR);
        if (preg_match('/\{\{\s*(?:password|otp|secret|card|token|raw_body)\s*\}\}/i', $encoded) === 1) {
            throw new InvalidArgumentException('Configuration template contains a prohibited variable.');
        }
    }

    /** @param array<string, mixed> $value @return array<string, mixed> */
    private static function canonicalize(array $value): array
    {
        ksort($value);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = array_is_list($item)
                    ? array_map(static fn (mixed $entry): mixed => is_array($entry) ? self::canonicalize($entry) : $entry, $item)
                    : self::canonicalize($item);
            }
        }
        return $value;
    }
}
