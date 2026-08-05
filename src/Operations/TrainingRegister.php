<?php

declare(strict_types=1);

namespace Sabri\CF02\Operations;

use DateTimeImmutable;
use InvalidArgumentException;

final class TrainingRegister
{
    /** @var array<string, array{modules:list<string>,expires_at:DateTimeImmutable,verified_by:string}> */
    private array $records = [];

    /** @param list<string> $modules */
    public function certify(
        string $staffReference,
        array $modules,
        DateTimeImmutable $expiresAt,
        string $verifiedBy,
        DateTimeImmutable $at
    ): void {
        if (trim($staffReference) === '' || trim($verifiedBy) === '' || $expiresAt <= $at) {
            throw new InvalidArgumentException('Training certification identity and expiry are required.');
        }
        $required = ['privacy', 'security', 'appeals_fairness', 'emergency_diversion', 'accessibility', 'sla_operations'];
        foreach ($required as $module) {
            if (!in_array($module, $modules, true)) {
                throw new InvalidArgumentException('Training certification is missing a required module.');
            }
        }
        if (count($modules) !== count(array_unique($modules))) {
            throw new InvalidArgumentException('Duplicate training modules are prohibited.');
        }
        $this->records[$staffReference] = [
            'modules' => $modules,
            'expires_at' => $expiresAt,
            'verified_by' => $verifiedBy,
        ];
    }

    public function isReady(string $staffReference, DateTimeImmutable $at): bool
    {
        $record = $this->records[$staffReference] ?? null;
        return $record !== null && $record['expires_at'] > $at;
    }

    /** @param list<string> $staffReferences */
    public function readiness(array $staffReferences, DateTimeImmutable $at): array
    {
        $missing = [];
        foreach ($staffReferences as $staffReference) {
            if (!is_string($staffReference) || !$this->isReady($staffReference, $at)) {
                $missing[] = (string) $staffReference;
            }
        }
        return ['ready' => $missing === [], 'missing_or_expired' => $missing];
    }
}
