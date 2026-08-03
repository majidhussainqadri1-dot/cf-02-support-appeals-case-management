<?php

declare(strict_types=1);

namespace Sabri\CF02\Intake;

use DomainException;
use Sabri\CF02\Domain\SupportCaseId;

final class IntakeLedger
{
    /** @var array<string, array{fingerprint:string, case_id:SupportCaseId}> */
    private array $records = [];

    public function register(IntakeRequest $request, SupportCaseId $proposedCaseId): SupportCaseId
    {
        $key = $request->idempotencyKey()->value();
        $fingerprint = $request->fingerprint();
        $existing = $this->records[$key] ?? null;

        if (is_array($existing)) {
            if (!hash_equals($existing['fingerprint'], $fingerprint)) {
                throw new DomainException('Intake idempotency key was reused with a different normalized payload.');
            }

            return $existing['case_id'];
        }

        $this->records[$key] = [
            'fingerprint' => $fingerprint,
            'case_id' => $proposedCaseId,
        ];

        return $proposedCaseId;
    }

    public function count(): int
    {
        return count($this->records);
    }
}
