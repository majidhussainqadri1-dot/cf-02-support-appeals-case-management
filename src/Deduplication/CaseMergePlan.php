<?php

declare(strict_types=1);

namespace Sabri\CF02\Deduplication;

use DomainException;
use InvalidArgumentException;
use Sabri\CF02\Domain\SupportCaseId;

final class CaseMergePlan
{
    private string $status = 'proposed';

    public function __construct(
        private readonly SupportCaseId $primaryCaseId,
        private readonly SupportCaseId $secondaryCaseId,
        private readonly string $primaryRequesterReference,
        private readonly string $secondaryRequesterReference,
        private readonly string $reason,
        private readonly string $previewHash
    ) {
        if ($primaryCaseId->equals($secondaryCaseId)) {
            throw new InvalidArgumentException('A case cannot be merged into itself.');
        }

        if (!hash_equals($primaryRequesterReference, $secondaryRequesterReference)) {
            throw new InvalidArgumentException('Cross-requester case merge is prohibited.');
        }

        if (trim($reason) === '' || preg_match('/^[a-f0-9]{64}$/', $previewHash) !== 1) {
            throw new InvalidArgumentException('Merge reason and immutable preview hash are required.');
        }
    }

    public function apply(string $confirmedPreviewHash): void
    {
        if ($this->status !== 'proposed') {
            throw new DomainException('Only a proposed merge may be applied.');
        }

        if (!hash_equals($this->previewHash, $confirmedPreviewHash)) {
            throw new DomainException('Merge preview changed; refresh and confirm again.');
        }

        $this->status = 'applied';
    }

    public function reverse(string $reason): void
    {
        if ($this->status !== 'applied') {
            throw new DomainException('Only an applied merge may be reversed.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Split reason is required.');
        }

        $this->status = 'reversed';
    }

    public function status(): string { return $this->status; }
    public function redirectCaseId(): SupportCaseId { return $this->primaryCaseId; }
    public function sourceCaseId(): SupportCaseId { return $this->secondaryCaseId; }
    public function reason(): string { return $this->reason; }
}
