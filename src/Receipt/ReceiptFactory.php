<?php

declare(strict_types=1);

namespace Sabri\CF02\Receipt;

use DateTimeImmutable;
use Sabri\CF02\Domain\SupportCaseId;
use Sabri\CF02\Intake\IntakeChannel;
use Sabri\CF02\Intake\IntakeRequest;
use Sabri\CF02\Intake\TriageDecision;

final class ReceiptFactory
{
    public function create(
        SupportCaseId $caseId,
        IntakeRequest $request,
        TriageDecision $triage,
        bool $deliveryProviderAvailable,
        ?DateTimeImmutable $receivedAt = null
    ): CaseReceipt {
        $receivedAt ??= new DateTimeImmutable('now');
        $deliveryStatus = $request->channel() === IntakeChannel::Web
            ? 'not_applicable'
            : ($deliveryProviderAvailable ? 'sent' : 'queued');

        return new CaseReceipt(
            $caseId,
            $request->categoryKey(),
            $receivedAt,
            'new',
            $triage->humanReviewRequired() ? 'Human triage and assignment' : 'Queue assignment',
            match ($triage->priority()) {
                'P1' => 'Immediate specialist review; ordinary SLA is not a substitute for emergency help',
                'P2' => 'Priority response window according to the active SLA policy',
                default => 'Standard response window according to the active SLA policy',
            },
            'This support service does not provide emergency diagnosis, prescription or emergency response.',
            $request->channel()->value,
            $deliveryStatus,
            $request->senderVerified() ? 'verified' : 'unverified'
        );
    }
}
