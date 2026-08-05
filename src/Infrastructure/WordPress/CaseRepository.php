<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use DateTimeImmutable;
use RuntimeException;
use Sabri\CF02\Domain\SupportCaseId;

final class CaseRepository
{
    private object $wpdb;
    private string $casesTable;
    private string $messagesTable;

    public function __construct()
    {
        global $wpdb;
        if (!is_object($wpdb) || !isset($wpdb->prefix)) {
            throw new RuntimeException('WordPress database adapter is unavailable.');
        }
        $this->wpdb = $wpdb;
        $this->casesTable = $wpdb->prefix . 'cf02_cases';
        $this->messagesTable = $wpdb->prefix . 'cf02_messages';
    }

    /** @return array<string, mixed> */
    public function createCase(
        string $requesterReference,
        string $category,
        string $priority,
        string $severity,
        string $queueKey,
        string $locale,
        string $safeSubject,
        DateTimeImmutable $at
    ): array {
        $caseId = SupportCaseId::generate();
        $now = $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        $inserted = $this->wpdb->insert($this->casesTable, [
            'case_uuid' => $caseId->value(),
            'requester_ref' => $requesterReference,
            'category' => $category,
            'priority' => $priority,
            'severity' => $severity,
            'state' => 'new',
            'queue_key' => $queueKey,
            'owner_ref' => null,
            'locale' => $locale,
            'safe_subject' => $safeSubject,
            'record_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%d','%s','%s']);
        if ($inserted !== 1) {
            throw new RuntimeException('Case persistence failed.');
        }
        return $this->getCase($caseId, $requesterReference) ?? throw new RuntimeException('Created case could not be reloaded.');
    }

    /** @return array<string, mixed>|null */
    public function getCase(SupportCaseId $caseId, string $requesterReference): ?array
    {
        $sql = $this->wpdb->prepare(
            "SELECT case_uuid, requester_ref, category, priority, severity, state, queue_key, owner_ref, locale, safe_subject, record_version, created_at, updated_at, closed_at
             FROM {$this->casesTable} WHERE case_uuid = %s AND requester_ref = %s LIMIT 1",
            $caseId->value(),
            $requesterReference
        );
        $row = $this->wpdb->get_row($sql, ARRAY_A);
        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function listRequesterCases(string $requesterReference, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $sql = $this->wpdb->prepare(
            "SELECT case_uuid, category, priority, state, queue_key, locale, safe_subject, record_version, created_at, updated_at
             FROM {$this->casesTable} WHERE requester_ref = %s ORDER BY updated_at DESC, id DESC LIMIT %d OFFSET %d",
            $requesterReference,
            $limit,
            $offset
        );
        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    public function addRequesterMessage(
        SupportCaseId $caseId,
        string $requesterReference,
        string $channel,
        string $ciphertext,
        string $contentHash,
        string $idempotencyKey,
        DateTimeImmutable $at
    ): string {
        if ($this->getCase($caseId, $requesterReference) === null) {
            throw new RuntimeException('Case not found or not authorized.');
        }
        if (preg_match('/^[a-f0-9]{64}$/', $contentHash) !== 1) {
            throw new RuntimeException('Message content hash is invalid.');
        }
        $messageId = 'CF02-MSG-' . strtoupper(substr(hash('sha256', $requesterReference . "\0" . $caseId->value() . "\0" . $idempotencyKey), 0, 20));
        $existingSql = $this->wpdb->prepare(
            "SELECT message_uuid, case_uuid, author_ref, channel, body_hash FROM {$this->messagesTable} WHERE message_uuid = %s LIMIT 1",
            $messageId
        );
        $existing = $this->wpdb->get_row($existingSql, ARRAY_A);
        if (is_array($existing)) {
            if (!hash_equals((string) $existing['case_uuid'], $caseId->value())
                || !hash_equals((string) $existing['author_ref'], $requesterReference)
                || !hash_equals((string) $existing['channel'], $channel)
                || !hash_equals((string) $existing['body_hash'], $contentHash)) {
                throw new RuntimeException('Message idempotency collision.');
            }
            return (string) $existing['message_uuid'];
        }
        $inserted = $this->wpdb->insert($this->messagesTable, [
            'message_uuid' => $messageId,
            'case_uuid' => $caseId->value(),
            'author_ref' => $requesterReference,
            'visibility' => 'requester',
            'channel' => $channel,
            'body_ciphertext' => $ciphertext,
            'body_hash' => $contentHash,
            'record_version' => 1,
            'created_at' => $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
        ], ['%s','%s','%s','%s','%s','%s','%s','%d','%s']);
        if ($inserted !== 1) {
            throw new RuntimeException('Message persistence failed.');
        }
        return $messageId;
    }
}
