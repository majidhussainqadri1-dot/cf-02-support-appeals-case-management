<?php

declare(strict_types=1);

namespace Sabri\CF02\Receipt;

use DateTimeImmutable;
use InvalidArgumentException;
use Sabri\CF02\Domain\SupportCaseId;

final class CaseReceipt
{
    public function __construct(
        private readonly SupportCaseId $caseId,
        private readonly string $category,
        private readonly DateTimeImmutable $receivedAt,
        private readonly string $status,
        private readonly string $nextStep,
        private readonly string $slaRange,
        private readonly string $emergencyBoundary,
        private readonly string $replyMethod,
        private readonly string $deliveryStatus
    ) {
        foreach ([$category, $status, $nextStep, $slaRange, $emergencyBoundary, $replyMethod] as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('Receipt fields must not be blank.');
            }
        }

        if (!in_array($deliveryStatus, ['queued', 'sent', 'failed', 'not_applicable'], true)) {
            throw new InvalidArgumentException('Invalid receipt delivery status.');
        }
    }

    public function caseId(): SupportCaseId { return $this->caseId; }
    public function category(): string { return $this->category; }
    public function receivedAt(): DateTimeImmutable { return $this->receivedAt; }
    public function status(): string { return $this->status; }
    public function nextStep(): string { return $this->nextStep; }
    public function slaRange(): string { return $this->slaRange; }
    public function emergencyBoundary(): string { return $this->emergencyBoundary; }
    public function replyMethod(): string { return $this->replyMethod; }
    public function deliveryStatus(): string { return $this->deliveryStatus; }
}
