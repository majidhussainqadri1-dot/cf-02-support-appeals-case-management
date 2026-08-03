<?php

declare(strict_types=1);

namespace Sabri\CF02\Feedback;

use DateTimeImmutable;
use InvalidArgumentException;
use Sabri\CF02\Domain\SupportCaseId;

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
        if (trim($respondentPseudonym) === '') {
            throw new InvalidArgumentException('Feedback respondent pseudonym is required.');
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
        if ($comment !== null && mb_strlen($comment) > 1000) {
            throw new InvalidArgumentException('Feedback comment exceeds the bounded limit.');
        }
    }

    public function caseId(): SupportCaseId { return $this->caseId; }
    public function respondentPseudonym(): string { return $this->respondentPseudonym; }
    public function rating(): ?int { return $this->rating; }
    public function comment(): ?string { return $this->comment; }
    public function optedOut(): bool { return $this->optedOut; }
    public function submittedAt(): DateTimeImmutable { return $this->submittedAt; }
}
