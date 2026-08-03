<?php

declare(strict_types=1);

namespace Sabri\CF02\Attachment;

use DateTimeImmutable;
use InvalidArgumentException;
use Sabri\CF02\Domain\SupportCaseId;

final class AttachmentRecord
{
    private AttachmentState $state;
    private ?string $redactedReference = null;

    public function __construct(
        private readonly string $attachmentId,
        private readonly SupportCaseId $caseId,
        private readonly string $sha256,
        private readonly string $mimeType,
        private readonly int $sizeBytes,
        private readonly string $purpose,
        private readonly DateTimeImmutable $consentedAt,
        private readonly string $storageReference,
        private readonly string $dataClass,
        private readonly bool $specializedVaultReference = false,
        private readonly bool $requesterVisible = true
    ) {
        if (trim($attachmentId) === '' || preg_match('/^[a-f0-9]{64}$/', strtolower($sha256)) !== 1) {
            throw new InvalidArgumentException('Attachment identity and SHA-256 are required.');
        }

        if (preg_match('/^[a-z0-9.+-]+\/[a-z0-9.+-]+$/i', $mimeType) !== 1) {
            throw new InvalidArgumentException('Invalid MIME type.');
        }

        if ($sizeBytes < 1 || $sizeBytes > 26214400) {
            throw new InvalidArgumentException('Attachment size must be between 1 byte and 25 MiB.');
        }

        if (trim($purpose) === '' || trim($storageReference) === '') {
            throw new InvalidArgumentException('Attachment purpose and private storage reference are required.');
        }

        if (!in_array($dataClass, ['C1', 'C2', 'C3', 'C4'], true)) {
            throw new InvalidArgumentException('Invalid attachment data class.');
        }

        if ($dataClass === 'C4' && !$specializedVaultReference) {
            throw new InvalidArgumentException('C4 evidence must use a specialized vault reference.');
        }

        $this->state = AttachmentState::Uploaded;
    }

    public function transition(AttachmentState $to, ?string $redactedReference = null): void
    {
        (new AttachmentStateMachine())->assertTransition($this->state, $to);

        if ($to === AttachmentState::Redacted) {
            if ($redactedReference === null || trim($redactedReference) === '') {
                throw new InvalidArgumentException('Redacted attachment reference is required.');
            }
            $this->redactedReference = $redactedReference;
        }

        $this->state = $to;
    }

    public function state(): AttachmentState { return $this->state; }
    public function attachmentId(): string { return $this->attachmentId; }
    public function caseId(): SupportCaseId { return $this->caseId; }
    public function sha256(): string { return strtolower($this->sha256); }
    public function dataClass(): string { return $this->dataClass; }
    public function consentedAt(): DateTimeImmutable { return $this->consentedAt; }

    public function identityFingerprint(): string
    {
        return hash('sha256', implode('|', [
            $this->caseId->value(),
            $this->attachmentId,
            strtolower($this->sha256),
            $this->mimeType,
            (string) $this->sizeBytes,
            $this->purpose,
            $this->dataClass,
        ]));
    }

    public function visibleToRequester(SupportCaseId $requestedCaseId): bool
    {
        return $this->downloadReference($requestedCaseId, 'requester') !== null;
    }

    public function downloadReference(SupportCaseId $requestedCaseId, string $actorRole): ?string
    {
        if (!$this->caseId->equals($requestedCaseId)) {
            return null;
        }

        if (!in_array($this->state, [AttachmentState::Available, AttachmentState::Redacted], true)) {
            return null;
        }

        if ($actorRole === 'requester') {
            if (!$this->requesterVisible) {
                return null;
            }
            return $this->state === AttachmentState::Redacted ? $this->redactedReference : $this->storageReference;
        }

        if ($this->dataClass === 'C4' && !in_array($actorRole, ['sensitive_liaison', 'appeal_reviewer'], true)) {
            return null;
        }

        if (!in_array($actorRole, ['support_agent', 'specialist_agent', 'team_lead', 'sensitive_liaison', 'appeal_reviewer'], true)) {
            return null;
        }

        return $this->state === AttachmentState::Redacted && $this->redactedReference !== null
            ? $this->redactedReference
            : $this->storageReference;
    }
}
