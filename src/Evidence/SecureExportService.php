<?php

declare(strict_types=1);

namespace Sabri\CF02\Evidence;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Sabri\CF02\Authorization\AccessContext;
use Sabri\CF02\Domain\SupportCaseId;
use Sabri\CF02\Security\SensitiveContentDetector;

final class SecureExportService
{
    /**
     * @param array<string, string> $safeFiles filename => content
     * @return array{manifest:ExportManifest,files:array<string,string>}
     */
    public function build(
        AccessContext $context,
        SupportCaseId $caseId,
        string $purpose,
        array $safeFiles,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt
    ): array {
        if (!$context->hasCapability('case.export') || !$context->isAssignedTo($caseId)) {
            throw new DomainException('Bounded export capability and case assignment are required.');
        }
        if (!in_array($purpose, ['privacy.rights', 'appeal.review', 'legal.hold'], true)) {
            throw new InvalidArgumentException('Unsupported export purpose.');
        }
        if ($safeFiles === [] || count($safeFiles) > 100) {
            throw new InvalidArgumentException('Export file set must be bounded.');
        }

        $hashes = [];
        foreach ($safeFiles as $name => $content) {
            if (!is_string($name) || !is_string($content) || strlen($content) > 5_000_000) {
                throw new InvalidArgumentException('Invalid export file.');
            }
            if (preg_match('/^[A-Za-z0-9_.-]{1,120}$/', $name) !== 1) {
                throw new InvalidArgumentException('Invalid export filename.');
            }
            if (SensitiveContentDetector::containsProhibitedSecret($content)) {
                throw new DomainException('Export contains prohibited secret, card or credential material.');
            }
            $hashes[$name] = hash('sha256', $content);
        }

        return [
            'manifest' => ExportManifest::create(
                $caseId,
                $context->actorReference(),
                $purpose,
                $hashes,
                $createdAt,
                $expiresAt
            ),
            'files' => $safeFiles,
        ];
    }
}
