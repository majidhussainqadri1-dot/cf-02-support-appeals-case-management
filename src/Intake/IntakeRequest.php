<?php

declare(strict_types=1);

namespace Sabri\CF02\Intake;

use InvalidArgumentException;
use Sabri\CF02\Configuration\SupportTaxonomy;
use Sabri\CF02\Security\SensitiveContentDetector;

final class IntakeRequest
{
    /**
     * @param array<string, string> $fields
     * @param list<string> $accessibilityNeeds
     * @param list<string> $attachmentReferences
     */
    public function __construct(
        private readonly string $requesterReference,
        private readonly IntakeChannel $channel,
        private readonly string $sourceMessageId,
        private readonly bool $senderVerified,
        private readonly string $categoryKey,
        private readonly string $description,
        private readonly array $fields,
        private readonly string $impact,
        private readonly string $urgency,
        private readonly string $language,
        private readonly array $accessibilityNeeds = [],
        private readonly bool $diagnosticsConsent = false,
        private readonly array $attachmentReferences = []
    ) {
        if (trim($requesterReference) === '' || trim($sourceMessageId) === '') {
            throw new InvalidArgumentException('Requester and source message identifiers are required.');
        }

        $taxonomy = SupportTaxonomy::defaults();
        if (!isset($taxonomy[$categoryKey])) {
            throw new InvalidArgumentException('Unknown support category.');
        }

        $trimmedDescription = trim($description);
        $length = function_exists('mb_strlen') ? mb_strlen($trimmedDescription) : strlen($trimmedDescription);
        if ($length < 10 || $length > 10000) {
            throw new InvalidArgumentException('Description must contain 10 to 10000 characters.');
        }

        if (!in_array($impact, ['low', 'medium', 'high', 'critical'], true)) {
            throw new InvalidArgumentException('Invalid impact value.');
        }

        if (!in_array($urgency, ['normal', 'urgent', 'immediate'], true)) {
            throw new InvalidArgumentException('Invalid urgency value.');
        }

        if (preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $language) !== 1) {
            throw new InvalidArgumentException('Language must be a BCP 47-style identifier.');
        }

        self::assertStringMap($fields, 'intake fields');
        self::assertStringList($accessibilityNeeds, 'accessibility needs');
        self::assertStringList($attachmentReferences, 'attachment references');

        foreach ($taxonomy[$categoryKey]->intakeFields() as $requiredField) {
            if (!isset($fields[$requiredField]) || trim($fields[$requiredField]) === '') {
                throw new InvalidArgumentException(sprintf('Required intake field is missing: %s.', $requiredField));
            }
        }

        $content = $description . "\n" . implode("\n", array_values($fields));
        if (SensitiveContentDetector::containsProhibitedSecret($content)) {
            throw new InvalidArgumentException('Passwords, OTPs, payment-card data or private keys must not be placed in an ordinary support intake.');
        }
    }

    public function requesterReference(): string { return $this->requesterReference; }
    public function channel(): IntakeChannel { return $this->channel; }
    public function sourceMessageId(): string { return $this->sourceMessageId; }
    public function senderVerified(): bool { return $this->senderVerified; }
    public function categoryKey(): string { return $this->categoryKey; }
    public function description(): string { return $this->description; }
    /** @return array<string, string> */ public function fields(): array { return $this->fields; }
    public function impact(): string { return $this->impact; }
    public function urgency(): string { return $this->urgency; }
    public function language(): string { return $this->language; }
    /** @return list<string> */ public function accessibilityNeeds(): array { return $this->accessibilityNeeds; }
    public function diagnosticsConsent(): bool { return $this->diagnosticsConsent; }
    /** @return list<string> */ public function attachmentReferences(): array { return $this->attachmentReferences; }
    public function idempotencyKey(): IdempotencyKey { return IdempotencyKey::forIntake($this->channel, $this->requesterReference, $this->sourceMessageId); }

    public function fingerprint(): string
    {
        $fields = $this->fields;
        ksort($fields);
        $accessibility = $this->accessibilityNeeds;
        sort($accessibility);
        $attachments = $this->attachmentReferences;
        sort($attachments);

        return hash('sha256', json_encode([
            'requester' => $this->requesterReference,
            'channel' => $this->channel->value,
            'source_message_id' => $this->sourceMessageId,
            'category' => $this->categoryKey,
            'description' => $this->description,
            'fields' => $fields,
            'impact' => $this->impact,
            'urgency' => $this->urgency,
            'language' => $this->language,
            'accessibility' => $accessibility,
            'diagnostics_consent' => $this->diagnosticsConsent,
            'attachments' => $attachments,
        ], JSON_THROW_ON_ERROR));
    }

    /** @param array<mixed> $values */
    private static function assertStringList(array $values, string $label): void
    {
        foreach ($values as $value) {
            if (!is_string($value) || trim($value) === '') {
                throw new InvalidArgumentException(sprintf('Invalid %s entry.', $label));
            }
        }

        if (count($values) !== count(array_unique($values))) {
            throw new InvalidArgumentException(sprintf('Duplicate %s entries are not allowed.', $label));
        }
    }

    /** @param array<mixed> $values */
    private static function assertStringMap(array $values, string $label): void
    {
        foreach ($values as $key => $value) {
            if (!is_string($key) || preg_match('/^[a-z][a-z0-9_]*$/', $key) !== 1 || !is_string($value)) {
                throw new InvalidArgumentException(sprintf('Invalid %s.', $label));
            }
        }
    }
}
