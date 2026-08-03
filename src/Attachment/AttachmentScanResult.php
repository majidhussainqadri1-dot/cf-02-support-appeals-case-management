<?php

declare(strict_types=1);

namespace Sabri\CF02\Attachment;

use DateTimeImmutable;
use InvalidArgumentException;

final class AttachmentScanResult
{
    public function __construct(
        private readonly string $sha256,
        private readonly string $detectedMimeType,
        private readonly string $verdict,
        private readonly string $scannerName,
        private readonly string $scannerVersion,
        private readonly DateTimeImmutable $scannedAt
    ) {
        if (preg_match('/^[a-f0-9]{64}$/', strtolower($sha256)) !== 1) {
            throw new InvalidArgumentException('Scan result requires a valid SHA-256.');
        }
        if (preg_match('/^[a-z0-9.+-]+\/[a-z0-9.+-]+$/i', $detectedMimeType) !== 1) {
            throw new InvalidArgumentException('Scan result requires a detected MIME type.');
        }
        if (!in_array($verdict, ['clean', 'infected', 'error'], true)) {
            throw new InvalidArgumentException('Invalid scan verdict.');
        }
        if (trim($scannerName) === '' || preg_match('/^\d+\.\d+(?:\.\d+)?(?:[-+][0-9A-Za-z.-]+)?$/', $scannerVersion) !== 1) {
            throw new InvalidArgumentException('Scanner name and semantic version are required.');
        }
    }

    public function sha256(): string { return strtolower($this->sha256); }
    public function detectedMimeType(): string { return strtolower($this->detectedMimeType); }
    public function verdict(): string { return $this->verdict; }
    public function scannerName(): string { return $this->scannerName; }
    public function scannerVersion(): string { return $this->scannerVersion; }
    public function scannedAt(): DateTimeImmutable { return $this->scannedAt; }
}
