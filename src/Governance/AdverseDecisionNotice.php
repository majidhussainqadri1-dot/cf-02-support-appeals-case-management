<?php

declare(strict_types=1);

namespace Sabri\CF02\Governance;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Evidence-minimal, accessible statement-of-reasons envelope for any adverse
 * support/native decision coordinated through CF-02.
 */
final class AdverseDecisionNotice
{
    /** @param list<string> $evidenceSummary */
    public function __construct(
        private readonly string $decisionReference,
        private readonly string $reason,
        private readonly string $policyVersion,
        private readonly array $evidenceSummary,
        private readonly string $remedy,
        private readonly string $appealRoute,
        private readonly DateTimeImmutable $appealDeadline,
        private readonly DateTimeImmutable $issuedAt,
        private readonly string $locale = 'ur-PK'
    ) {
        foreach ([
            'decision reference' => $decisionReference,
            'reason' => $reason,
            'policy version' => $policyVersion,
            'remedy' => $remedy,
            'appeal route' => $appealRoute,
        ] as $label => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException(sprintf('Adverse-decision %s is required.', $label));
            }
        }
        if ($evidenceSummary === []) {
            throw new InvalidArgumentException('Adverse-decision evidence summary is required.');
        }
        foreach ($evidenceSummary as $item) {
            if (!is_string($item) || trim($item) === '' || strlen($item) > 1000) {
                throw new InvalidArgumentException('Adverse-decision evidence summary contains an invalid item.');
            }
        }
        if (count($evidenceSummary) !== count(array_unique($evidenceSummary))) {
            throw new InvalidArgumentException('Duplicate adverse-decision evidence summaries are prohibited.');
        }
        if ($appealDeadline <= $issuedAt) {
            throw new InvalidArgumentException('Appeal deadline must follow notice issuance.');
        }
        if (preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $locale) !== 1) {
            throw new InvalidArgumentException('Adverse-decision locale is invalid.');
        }
    }

    /** @return array<string,mixed> */
    public function projection(): array
    {
        $payload = [
            'decision_reference' => $this->decisionReference,
            'reason' => $this->reason,
            'policy_version' => $this->policyVersion,
            'evidence_summary' => $this->evidenceSummary,
            'remedy' => $this->remedy,
            'appeal_route' => $this->appealRoute,
            'appeal_deadline' => $this->appealDeadline->format(DATE_ATOM),
            'issued_at' => $this->issuedAt->format(DATE_ATOM),
            'locale' => $this->locale,
        ];
        $payload['notice_hash'] = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $payload;
    }
}
