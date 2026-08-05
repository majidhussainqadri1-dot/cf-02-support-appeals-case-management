<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use DateTimeImmutable;
use RuntimeException;
use Sabri\CF02\Configuration\CategoryRoutingPolicy;
use Sabri\CF02\Contracts\SupportContractCatalog;
use Sabri\CF02\Domain\SupportCaseId;
use Sabri\CF02\Governance\ServiceEqualityPolicy;
use Sabri\CF02\Security\MutationEnvelope;
use Throwable;

final class IntakeRepository
{
    private object $wpdb;
    private string $table;

    public function __construct(private readonly CaseRepository $cases)
    {
        global $wpdb;
        if (!is_object($wpdb) || !isset($wpdb->prefix)) {
            throw new RuntimeException('WordPress database adapter is unavailable.');
        }
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'cf02_intake_replay';
    }

    /** @param array<string, scalar|null> $payload @return array{case:array<string,mixed>,replayed:bool} */
    public function createOrReplay(
        string $requesterReference,
        string $idempotencyKey,
        array $payload,
        DateTimeImmutable $at
    ): array {
        ServiceEqualityPolicy::assertNoPrivilegeSignals($payload);
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:@-]{2,190}$/', $requesterReference) !== 1
            || preg_match('/^[A-Za-z0-9._:-]{16,128}$/', $idempotencyKey) !== 1) {
            throw new RuntimeException('Intake requester or idempotency identity is invalid.');
        }
        $category = CategoryRoutingPolicy::normalize((string) ($payload['category'] ?? ''));
        SupportContractCatalog::assertCategory($category);
        $priority = strtoupper((string) ($payload['priority'] ?? ''));
        $severity = (string) ($payload['severity'] ?? '');
        if (!in_array($priority, ['P1','P2','P3','P4'], true)
            || !in_array($severity, ['normal','S1','S2','S3','S4'], true)) {
            throw new RuntimeException('Intake priority or severity is invalid.');
        }
        $payload['category'] = $category;
        $payload['priority'] = $priority;
        $payload['severity'] = $severity;
        $payload['queue'] = CategoryRoutingPolicy::queueFor($category);
        $payload['locale'] = ApiInput::locale($payload['locale'] ?? null);
        $payload['subject'] = ApiInput::safeSingleLine($payload['subject'] ?? '', 'Case subject', 191, true);
        $fingerprint = MutationEnvelope::fingerprint($payload);
        $existing = $this->find($idempotencyKey);
        if ($existing !== null) {
            return $this->resolveExisting($existing, $requesterReference, $fingerprint);
        }

        $this->wpdb->query('START TRANSACTION');
        try {
            $existing = $this->find($idempotencyKey, true);
            if ($existing !== null) {
                $this->wpdb->query('COMMIT');
                return $this->resolveExisting($existing, $requesterReference, $fingerprint);
            }

            $case = $this->cases->createCase(
                $requesterReference,
                (string) $payload['category'],
                (string) $payload['priority'],
                (string) $payload['severity'],
                (string) $payload['queue'],
                (string) $payload['locale'],
                (string) $payload['subject'],
                $at
            );
            $caseId = SupportCaseId::fromString((string) $case['case_uuid']);
            $createdAt = $at->setTimezone(new \DateTimeZone('UTC'));
            $inserted = $this->wpdb->insert($this->table, [
                'idempotency_key' => $idempotencyKey,
                'requester_ref' => $requesterReference,
                'payload_hash' => $fingerprint,
                'case_uuid' => $caseId->value(),
                'trace_id' => 'tr_' . bin2hex(random_bytes(16)),
                'created_at' => $createdAt->format('Y-m-d H:i:s.u'),
                'expires_at' => $createdAt->modify('+24 hours')->format('Y-m-d H:i:s.u'),
            ], ['%s','%s','%s','%s','%s','%s','%s']);
            if ($inserted !== 1) {
                throw new RuntimeException('Intake replay ledger persistence failed.');
            }
            $this->wpdb->query('COMMIT');
            return ['case' => $case, 'replayed' => false];
        } catch (Throwable $exception) {
            $this->wpdb->query('ROLLBACK');
            $existing = $this->find($idempotencyKey);
            if ($existing !== null) {
                return $this->resolveExisting($existing, $requesterReference, $fingerprint);
            }
            throw $exception;
        }
    }

    /** @return array<string,string>|null */
    private function find(string $idempotencyKey, bool $forUpdate = false): ?array
    {
        $sql = $this->wpdb->prepare(
            "SELECT idempotency_key, requester_ref, payload_hash, case_uuid FROM {$this->table} WHERE idempotency_key = %s LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : ''),
            $idempotencyKey
        );
        $row = $this->wpdb->get_row($sql, ARRAY_A);
        return is_array($row) ? array_map('strval', $row) : null;
    }

    /** @param array<string,string> $existing @return array{case:array<string,mixed>,replayed:bool} */
    private function resolveExisting(array $existing, string $requesterReference, string $fingerprint): array
    {
        if (!hash_equals($existing['requester_ref'], $requesterReference)
            || !hash_equals($existing['payload_hash'], $fingerprint)) {
            throw new RuntimeException('Intake idempotency key was reused with a different requester or payload.');
        }
        $case = $this->cases->getCase(SupportCaseId::fromString($existing['case_uuid']), $requesterReference);
        if ($case === null) {
            throw new RuntimeException('Intake replay points to an unavailable case.');
        }
        return ['case' => $case, 'replayed' => true];
    }
}
