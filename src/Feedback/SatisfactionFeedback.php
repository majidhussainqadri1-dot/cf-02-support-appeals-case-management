<?php

declare(strict_types=1);

namespace Sabri\CF02\Feedback;

use DateTimeImmutable;
use InvalidArgumentException;
use Sabri\CF02\Domain\SupportCaseId;
use Sabri\CF02\Security\SensitiveContentDetector;

final class SatisfactionFeedback
{
    public function __construct(
        private readonly SupportCaseId $caseId,
        private readonly string $respondentPseudonym,
        private readonly ?int $rating,
        private readonly ?string $comment,
        private readonly bool $optedOut,
        private readonly DateTimeImmutable $submittedAt
    ) {
        if (trim($respondentPseudonym) === '' || strlen($respondentPseudonym) > 128) {
            throw new InvalidArgumentException('Feedback respondent pseudonym is required and bounded.');
        }
        if ($optedOut) {
            if ($rating !== null || $comment !== null) {
                throw new InvalidArgumentException('Opted-out feedback cannot retain rating or comment.');
            }
            return;
        }
        if ($rating === null || $rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('Feedback rating must be between one and five.');
        }
        if ($comment !== null && strlen($comment) > 4000) {
            throw new InvalidArgumentException('Feedback comment exceeds the bounded byte limit.');
        }
        if ($comment !== null && SensitiveContentDetector::containsProhibitedSecret($comment)) {
            throw new InvalidArgumentException('Feedback cannot contain passwords, OTPs, card data or private keys.');
        }
    }

    public function caseId(): SupportCaseId { return $this->caseId; }
    public function respondentPseudonym(): string { return $this->respondentPseudonym; }
    public function rating(): ?int { return $this->rating; }
    public function comment(): ?string { return $this->comment; }
    public function optedOut(): bool { return $this->optedOut; }
    public function submittedAt(): DateTimeImmutable { return $this->submittedAt; }
}
