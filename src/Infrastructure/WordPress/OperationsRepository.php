<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Sabri\CF02\Authorization\PrincipalContext;
use Sabri\CF02\Contracts\SupportContractCatalog;
use Sabri\CF02\Domain\SupportCaseId;

/**
 * Transactional runtime repository for the complete CF-02 command/query surface.
 * It stores only CF-02 truth and versioned references to native-owner truth.
 */
final class OperationsRepository
{
    private object $wpdb;
    /** @var array<string,string> */ private array $tables;

    public function __construct()
    {
        global $wpdb;
        if (!is_object($wpdb) || !isset($wpdb->prefix)) {
            throw new RuntimeException('WordPress database adapter is unavailable.');
        }
        $this->wpdb = $wpdb;
        $prefix = (string) $wpdb->prefix;
        $this->tables = [
            'cases' => $prefix . 'cf02_cases',
            'messages' => $prefix . 'cf02_messages',
            'attachments' => $prefix . 'cf02_attachments',
            'assignments' => $prefix . 'cf02_assignments',
            'sla' => $prefix . 'cf02_sla_timers',
            'appeals' => $prefix . 'cf02_appeals',
            'dossiers' => $prefix . 'cf02_appeal_dossiers',
            'tasks' => $prefix . 'cf02_tasks',
            'commands' => $prefix . 'cf02_commands',
            'outbox' => $prefix . 'cf02_outbox',
            'holds' => $prefix . 'cf02_holds',
            'configuration' => $prefix . 'cf02_configuration',
            'audit' => $prefix . 'cf02_audit',
            'events' => $prefix . 'cf02_events',
            'command_payloads' => $prefix . 'cf02_command_payloads',
            'outbox_payloads' => $prefix . 'cf02_outbox_payloads',
            'representatives' => $prefix . 'cf02_representatives',
            'case_links' => $prefix . 'cf02_case_links',
            'inbound' => $prefix . 'cf02_inbound_receipts',
            'tokens' => $prefix . 'cf02_attachment_tokens',
            'merge_redirects' => $prefix . 'cf02_merge_redirects',
            'incident_links' => $prefix . 'cf02_incident_links',
            'metrics' => $prefix . 'cf02_metrics',
            'note_revisions' => $prefix . 'cf02_note_revisions',
            'quality' => $prefix . 'cf02_quality_reviews',
            'feedback' => $prefix . 'cf02_feedback',
            'retention' => $prefix . 'cf02_retention_ledger',
        ];
    }

    /** @return array<string,mixed> */
    public function caseForActor(SupportCaseId $caseId, PrincipalContext $context, bool $allowStaff = true): array
    {
        $row = $this->row($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['cases']} WHERE case_uuid=%s LIMIT 1",
            $caseId->value()
        ));
        if ($row === null) {
            throw new RuntimeException('Case not found.');
        }
        $requester = (string) $row['requester_ref'];
        if (hash_equals($requester, $context->actorReference()) || $context->represents($requester)) {
            return $row;
        }
        if (!$allowStaff || !$context->hasAnyCapability(
            'case.assigned.read', 'case.specialist.read', 'case.sensitive.read',
            'case.search.scoped', 'queue.manage', 'audit.sample.read'
        )) {
            throw new RuntimeException('Case not found.');
        }
        if ($context->hasAnyCapability('queue.manage', 'audit.sample.read')) {
            return $row;
        }
        $assigned = $this->value($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tables['assignments']} WHERE case_uuid=%s AND agent_ref=%s AND ended_at IS NULL",
            $caseId->value(),
            $context->actorReference()
        ));
        if ((int) $assigned < 1) {
            throw new RuntimeException('Case not found.');
        }
        return $row;
    }

    /** @return array<string,mixed> */
    public function appealForActor(string $appealId, PrincipalContext $context, bool $allowStaff = true): array
    {
        $row = $this->row($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['appeals']} WHERE appeal_uuid=%s LIMIT 1",
            $appealId
        ));
        if ($row === null) {
            throw new RuntimeException('Appeal not found.');
        }
        if (hash_equals((string) $row['appellant_ref'], $context->actorReference())
            || $context->represents((string) $row['appellant_ref'])) {
            return $row;
        }
        if ($allowStaff && $context->hasAnyCapability('appeal.queue.read', 'appeal.review', 'appeal.decision')) {
            if ($context->hasCapability('appeal.review') && $row['reviewer_ref'] !== null
                && !hash_equals((string) $row['reviewer_ref'], $context->actorReference())
                && !$context->hasCapability('appeal.queue.read')) {
                throw new RuntimeException('Appeal not found.');
            }
            return $row;
        }
        throw new RuntimeException('Appeal not found.');
    }

    /** @return list<array<string,mixed>> */
    public function listMyCases(PrincipalContext $context, int $limit, int $offset): array
    {
        $refs = array_values(array_unique(array_merge([$context->actorReference()], $context->representedRequesters(), $this->representedRefs($context))));
        $placeholders = implode(',', array_fill(0, count($refs), '%s'));
        $args = array_merge($refs, [max(1, min(100, $limit)), max(0, $offset)]);
        $sql = $this->wpdb->prepare(
            "SELECT case_uuid,requester_ref,category,priority,severity,state,queue_key,locale,safe_subject,record_version,created_at,updated_at
             FROM {$this->tables['cases']} WHERE requester_ref IN ({$placeholders})
             ORDER BY updated_at DESC,id DESC LIMIT %d OFFSET %d",
            ...$args
        );
        return $this->rows($sql);
    }

    /** @return list<array<string,mixed>> */
    public function assignedQueue(PrincipalContext $context, int $limit, int $offset, ?string $state = null): array
    {
        $where = 'a.agent_ref=%s AND a.ended_at IS NULL';
        $args = [$context->actorReference()];
        if ($state !== null && $state !== '') {
            $where .= ' AND c.state=%s';
            $args[] = $state;
        }
        $args[] = max(1, min(100, $limit));
        $args[] = max(0, $offset);
        $sql = $this->wpdb->prepare(
            "SELECT c.case_uuid,c.category,c.priority,c.severity,c.state,c.queue_key,c.owner_ref,c.locale,c.safe_subject,c.record_version,c.updated_at,
                    a.role_key,a.scopes_json,a.started_at
             FROM {$this->tables['cases']} c JOIN {$this->tables['assignments']} a ON a.case_uuid=c.case_uuid
             WHERE {$where} ORDER BY FIELD(c.priority,'P1','P2','P3','P4'),c.updated_at ASC LIMIT %d OFFSET %d",
            ...$args
        );
        return $this->rows($sql);
    }

    /** @return list<array<string,mixed>> */
    public function searchAuthorized(PrincipalContext $context, array $filters, int $limit, int $offset): array
    {
        $clauses = ['1=1'];
        $args = [];
        foreach (['state', 'category', 'priority', 'queue_key'] as $field) {
            if (isset($filters[$field]) && is_string($filters[$field]) && $filters[$field] !== '') {
                $clauses[] = "c.{$field}=%s";
                $args[] = $filters[$field];
            }
        }
        if (!$context->hasCapability('queue.manage')) {
            $clauses[] = 'EXISTS (SELECT 1 FROM ' . $this->tables['assignments'] . ' a WHERE a.case_uuid=c.case_uuid AND a.agent_ref=%s AND a.ended_at IS NULL)';
            $args[] = $context->actorReference();
        }
        $args[] = max(1, min(100, $limit));
        $args[] = max(0, $offset);
        $sql = $this->wpdb->prepare(
            "SELECT c.case_uuid,c.category,c.priority,c.severity,c.state,c.queue_key,c.owner_ref,c.locale,c.safe_subject,c.record_version,c.updated_at
             FROM {$this->tables['cases']} c WHERE " . implode(' AND ', $clauses) .
            " ORDER BY FIELD(c.priority,'P1','P2','P3','P4'),c.updated_at ASC LIMIT %d OFFSET %d",
            ...$args
        );
        return $this->rows($sql);
    }

    /** @return array<string,mixed> */
    public function mutateCase(
        SupportCaseId $caseId,
        PrincipalContext $context,
        int $expectedVersion,
        array $fields,
        string $command,
        string $event,
        string $purpose,
        string $idempotencyKey,
        array $eventPayload,
        DateTimeImmutable $at
    ): array {
        SupportContractCatalog::assertCommand($command);
        SupportContractCatalog::assertEvent($event);
        $this->caseForActor($caseId, $context);
        if ($this->eventReplay($caseId->value(), $event, $idempotencyKey, $eventPayload)) {
            return $this->caseForActor($caseId, $context);
        }
        $allowed = ['state', 'category', 'priority', 'severity', 'queue_key', 'owner_ref', 'closed_at'];
        foreach (array_keys($fields) as $field) {
            if (!in_array($field, $allowed, true)) {
                throw new RuntimeException('Unsupported case mutation field.');
            }
        }
        $this->transaction(function () use ($caseId, $context, $expectedVersion, $fields, $event, $purpose, $idempotencyKey, $eventPayload, $at): void {
            $data = array_merge($fields, [
                'record_version' => $expectedVersion + 1,
                'updated_at' => $this->mysqlTime($at),
            ]);
            $where = ['case_uuid' => $caseId->value(), 'record_version' => $expectedVersion];
            $updated = $this->wpdb->update($this->tables['cases'], $data, $where);
            if ($updated !== 1) {
                throw new RuntimeException('Stale case version or case mutation failed.');
            }
            $this->appendEvent('case', $caseId->value(), $event, $context, $purpose, $idempotencyKey, $eventPayload, $expectedVersion + 1, $at);
        });
        return $this->caseForActor($caseId, $context);
    }

    public function appendMessage(
        SupportCaseId $caseId,
        PrincipalContext $context,
        string $visibility,
        string $channel,
        string $ciphertext,
        string $contentHash,
        string $idempotencyKey,
        string $purpose,
        DateTimeImmutable $at
    ): string {
        $case = $this->caseForActor($caseId, $context);
        if (in_array((string) $case['state'], ['closed'], true)) {
            throw new RuntimeException('Closed cases cannot receive messages before governed reopen.');
        }
        if (!in_array($visibility, ['requester', 'internal', 'restricted'], true)
            || !in_array($channel, ['web', 'email', 'chat', 'system'], true)
            || preg_match('/^[a-f0-9]{64}$/', $contentHash) !== 1) {
            throw new RuntimeException('Message metadata is invalid.');
        }
        $messageId = 'CF02-MSG-' . strtoupper(substr(hash('sha256', $caseId->value() . "\0" . $context->actorReference() . "\0" . $idempotencyKey), 0, 20));
        $existing = $this->row($this->wpdb->prepare(
            "SELECT message_uuid,case_uuid,author_ref,visibility,channel,body_hash FROM {$this->tables['messages']} WHERE message_uuid=%s LIMIT 1",
            $messageId
        ));
        if ($existing !== null) {
            $same = hash_equals((string) $existing['case_uuid'], $caseId->value())
                && hash_equals((string) $existing['author_ref'], $context->actorReference())
                && hash_equals((string) $existing['visibility'], $visibility)
                && hash_equals((string) $existing['channel'], $channel)
                && hash_equals((string) $existing['body_hash'], $contentHash);
            if (!$same) {
                throw new RuntimeException('Message idempotency collision.');
            }
            return $messageId;
        }
        $this->transaction(function () use ($messageId, $caseId, $context, $visibility, $channel, $ciphertext, $contentHash, $idempotencyKey, $purpose, $at): void {
            $ok = $this->wpdb->insert($this->tables['messages'], [
                'message_uuid' => $messageId,
                'case_uuid' => $caseId->value(),
                'author_ref' => $context->actorReference(),
                'visibility' => $visibility,
                'channel' => $channel,
                'body_ciphertext' => $ciphertext,
                'body_hash' => $contentHash,
                'record_version' => 1,
                'created_at' => $this->mysqlTime($at),
            ]);
            if ($ok !== 1) {
                throw new RuntimeException('Message persistence failed.');
            }
            $event = $visibility === 'requester' && str_starts_with($context->actorReference(), 'user:')
                ? 'SupportUserReplied' : 'SupportAgentReplied';
            $this->appendEvent('case', $caseId->value(), $event, $context, $purpose, $idempotencyKey, [
                'message_ref' => $messageId, 'visibility' => $visibility, 'channel' => $channel,
            ], (int) $this->value($this->wpdb->prepare(
                "SELECT record_version FROM {$this->tables['cases']} WHERE case_uuid=%s",
                $caseId->value()
            )), $at);
        });
        return $messageId;
    }

    public function linkObject(
        SupportCaseId $caseId,
        PrincipalContext $context,
        string $ownerKey,
        string $objectType,
        string $objectRef,
        string $objectVersion,
        string $privacyClass,
        array $safeProjection,
        string $idempotencyKey,
        DateTimeImmutable $at
    ): string {
        $this->caseForActor($caseId, $context);
        if (trim($ownerKey) === '' || trim($objectType) === '' || trim($objectRef) === ''
            || trim($objectVersion) === '' || !in_array($privacyClass, ['C1','C2','C3','C4','C5'], true)) {
            throw new RuntimeException('Linked native object metadata is invalid.');
        }
        $projectionHash = hash('sha256', $this->json($safeProjection));
        $id = 'CF02-LNK-' . strtoupper(substr(hash('sha256', $ownerKey . "\0" . $objectType . "\0" . $objectRef . "\0" . $caseId->value()), 0, 20));
        $existing = $this->row($this->wpdb->prepare("SELECT * FROM {$this->tables['case_links']} WHERE link_uuid=%s LIMIT 1", $id));
        if ($existing !== null) {
            if (!hash_equals((string) $existing['projection_hash'], $projectionHash)
                || !hash_equals((string) $existing['object_version'], $objectVersion)) {
                $updated = $this->wpdb->update($this->tables['case_links'], [
                    'object_version' => $objectVersion, 'privacy_class' => $privacyClass,
                    'projection_hash' => $projectionHash, 'updated_at' => $this->mysqlTime($at),
                ], ['link_uuid' => $id, 'state' => 'active']);
                if ($updated !== 1) {
                    throw new RuntimeException('Linked object projection update conflicted.');
                }
            }
            $version = (int) $this->value($this->wpdb->prepare("SELECT record_version FROM {$this->tables['cases']} WHERE case_uuid=%s", $caseId->value()));
            $this->appendEvent('case', $caseId->value(), 'SupportDomainObjectLinked', $context, 'linked_domain_projection', $idempotencyKey, [
                'link_ref' => $id, 'owner_key' => $ownerKey, 'object_type' => $objectType, 'object_ref' => $objectRef,
                'object_version' => $objectVersion, 'projection_hash' => $projectionHash,
            ], $version, $at);
            return $id;
        }
        $ok = $this->wpdb->insert($this->tables['case_links'], [
            'link_uuid' => $id, 'case_uuid' => $caseId->value(), 'owner_key' => $ownerKey,
            'object_type' => $objectType, 'object_ref' => $objectRef, 'object_version' => $objectVersion,
            'privacy_class' => $privacyClass, 'projection_hash' => $projectionHash, 'state' => 'active',
            'created_at' => $this->mysqlTime($at), 'updated_at' => $this->mysqlTime($at),
        ]);
        if ($ok !== 1) {
            throw new RuntimeException('Linked object reference persistence failed.');
        }
        $version = (int) $this->value($this->wpdb->prepare("SELECT record_version FROM {$this->tables['cases']} WHERE case_uuid=%s", $caseId->value()));
        $this->appendEvent('case', $caseId->value(), 'SupportDomainObjectLinked', $context, 'linked_domain_projection', $idempotencyKey, [
            'link_ref' => $id, 'owner_key' => $ownerKey, 'object_type' => $objectType, 'object_ref' => $objectRef,
            'object_version' => $objectVersion, 'projection_hash' => $projectionHash,
        ], $version, $at);
        return $id;
    }

    public function ensureSlaTimer(SupportCaseId $caseId, string $priority, DateTimeImmutable $at): void
    {
        $existing = $this->row($this->wpdb->prepare("SELECT case_uuid FROM {$this->tables['sla']} WHERE case_uuid=%s LIMIT 1", $caseId->value()));
        if ($existing !== null) {
            return;
        }
        $defaults = [
            'policy_id' => 'cf02-default', 'policy_version' => '1.0.0',
            'first_response_minutes' => in_array($priority, ['P1','P2'], true) ? 240 : 1440,
            'update_minutes' => 1440,
            'resolution_minutes' => match ($priority) { 'P1' => 480, 'P2' => 1440, 'P3' => 4320, default => 7200 },
        ];
        /** @var mixed $configured */
        $configured = apply_filters('cf02_sla_policy_for_case', $defaults, $caseId->value(), $priority);
        if (!is_array($configured)) {
            throw new RuntimeException('SLA policy provider returned invalid evidence.');
        }
        $policy = array_merge($defaults, $configured);
        foreach (['policy_id','policy_version'] as $field) {
            if (!is_string($policy[$field]) || preg_match('/^[A-Za-z0-9._:-]{2,64}$/', $policy[$field]) !== 1) {
                throw new RuntimeException('SLA policy identity is invalid.');
            }
        }
        foreach (['first_response_minutes','update_minutes','resolution_minutes'] as $field) {
            if (!is_int($policy[$field]) || $policy[$field] < 1 || $policy[$field] > 525600) {
                throw new RuntimeException('SLA policy duration is invalid.');
            }
        }
        $ok = $this->wpdb->insert($this->tables['sla'], [
            'case_uuid' => $caseId->value(), 'policy_id' => $policy['policy_id'], 'policy_version' => $policy['policy_version'],
            'status' => 'running', 'first_response_deadline' => $this->mysqlTime($at->modify('+' . $policy['first_response_minutes'] . ' minutes')),
            'update_deadline' => $this->mysqlTime($at->modify('+' . $policy['update_minutes'] . ' minutes')),
            'resolution_deadline' => $this->mysqlTime($at->modify('+' . $policy['resolution_minutes'] . ' minutes')),
            'paused_at' => null, 'pause_reason' => null, 'evidence_ref' => null, 'record_version' => 1,
        ]);
        if ($ok !== 1) {
            throw new RuntimeException('SLA timer persistence failed.');
        }
    }

    public function pauseSla(SupportCaseId $caseId, string $reason, string $evidenceRef, DateTimeImmutable $at): void
    {
        if (!in_array($reason, ['waiting_user','waiting_provider','legal_hold'], true) || trim($evidenceRef) === '') {
            throw new RuntimeException('SLA pause reason or evidence is invalid.');
        }
        $row = $this->row($this->wpdb->prepare("SELECT record_version,status FROM {$this->tables['sla']} WHERE case_uuid=%s LIMIT 1", $caseId->value()));
        if ($row === null || !in_array((string) $row['status'], ['running','at_risk'], true)) {
            throw new RuntimeException('SLA timer is not eligible for pause.');
        }
        $updated = $this->wpdb->update($this->tables['sla'], [
            'status' => 'paused', 'paused_at' => $this->mysqlTime($at), 'pause_reason' => $reason,
            'evidence_ref' => $evidenceRef, 'record_version' => (int) $row['record_version'] + 1,
        ], ['case_uuid' => $caseId->value(), 'record_version' => (int) $row['record_version']]);
        if ($updated !== 1) {
            throw new RuntimeException('SLA pause conflicted.');
        }
    }

    public function resumeSla(SupportCaseId $caseId, string $evidenceRef, DateTimeImmutable $at): void
    {
        $row = $this->row($this->wpdb->prepare("SELECT * FROM {$this->tables['sla']} WHERE case_uuid=%s LIMIT 1", $caseId->value()));
        if ($row === null || (string) $row['status'] !== 'paused') {
            return;
        }
        if (trim($evidenceRef) === '' || $row['paused_at'] === null) {
            throw new RuntimeException('SLA resume evidence is invalid.');
        }
        $pausedAt = new DateTimeImmutable((string) $row['paused_at'], new DateTimeZone('UTC'));
        $seconds = max(0, $at->getTimestamp() - $pausedAt->getTimestamp());
        $shift = static fn (string $value): string => (new DateTimeImmutable($value, new DateTimeZone('UTC')))->modify('+' . $seconds . ' seconds')->format('Y-m-d H:i:s.u');
        $updated = $this->wpdb->update($this->tables['sla'], [
            'status' => 'running', 'first_response_deadline' => $shift((string) $row['first_response_deadline']),
            'update_deadline' => $shift((string) $row['update_deadline']),
            'resolution_deadline' => $shift((string) $row['resolution_deadline']),
            'paused_at' => null, 'pause_reason' => null, 'evidence_ref' => $evidenceRef,
            'record_version' => (int) $row['record_version'] + 1,
        ], ['case_uuid' => $caseId->value(), 'record_version' => (int) $row['record_version']]);
        if ($updated !== 1) {
            throw new RuntimeException('SLA resume conflicted.');
        }
    }

    /** @return list<array<string,mixed>> */
    public function dueSla(int $limit): array
    {
        return $this->rows($this->wpdb->prepare(
            "SELECT s.*,c.state,c.priority,c.owner_ref,c.requester_ref FROM {$this->tables['sla']} s
             JOIN {$this->tables['cases']} c ON c.case_uuid=s.case_uuid
             WHERE s.status IN ('running','at_risk') AND c.state NOT IN ('resolved','closed','withdrawn')
             AND (s.first_response_deadline<=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 30 MINUTE)
                  OR s.update_deadline<=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 30 MINUTE)
                  OR s.resolution_deadline<=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 30 MINUTE))
             ORDER BY LEAST(s.first_response_deadline,s.update_deadline,s.resolution_deadline) ASC LIMIT %d",
            max(1, min(250, $limit))
        ));
    }

    public function markSlaStatus(string $caseId, string $status, DateTimeImmutable $at): int
    {
        if (!in_array($status, ['at_risk','breached','running','resolved'], true)) {
            throw new RuntimeException('Invalid SLA status.');
        }
        $row = $this->row($this->wpdb->prepare("SELECT record_version FROM {$this->tables['sla']} WHERE case_uuid=%s", $caseId));
        if ($row === null) {
            throw new RuntimeException('SLA timer not found.');
        }
        $version = (int) $row['record_version'] + 1;
        $updated = $this->wpdb->update($this->tables['sla'], [
            'status' => $status, 'evidence_ref' => 'worker:' . $at->format(DATE_ATOM),
            'record_version' => $version,
        ], ['case_uuid' => $caseId, 'record_version' => (int) $row['record_version']]);
        if ($updated !== 1) {
            throw new RuntimeException('SLA status update conflicted.');
        }
        return $version;
    }

    /** @return array<string,mixed> */
    public function createAttachment(
        SupportCaseId $caseId,
        PrincipalContext $context,
        string $mimeType,
        int $size,
        string $sha256,
        string $purpose,
        string $privacyClass,
        string $idempotencyKey,
        DateTimeImmutable $at
    ): array {
        $this->caseForActor($caseId, $context);
        if ($size < 1 || $size > 25 * 1024 * 1024 || preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1
            || preg_match('/^[a-z0-9.+-]+\/[a-z0-9.+-]+$/i', $mimeType) !== 1
            || !in_array($privacyClass, ['C1','C2','C3','C4','C5'], true)
            || preg_match('/^[a-z][a-z0-9_]{2,63}$/', $purpose) !== 1) {
            throw new RuntimeException('Attachment metadata is invalid.');
        }
        $id = 'CF02-ATT-' . strtoupper(substr(hash('sha256', $caseId->value() . "\0" . $idempotencyKey), 0, 20));
        $existing = $this->row($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['attachments']} WHERE attachment_uuid=%s LIMIT 1", $id
        ));
        if ($existing !== null) {
            $same = hash_equals((string) $existing['case_uuid'], $caseId->value())
                && hash_equals((string) $existing['sha256'], $sha256)
                && hash_equals((string) $existing['mime_type'], $mimeType)
                && hash_equals((string) $existing['purpose'], $purpose)
                && hash_equals((string) $existing['privacy_class'], $privacyClass);
            if (!$same) {
                throw new RuntimeException('Attachment idempotency collision.');
            }
            return $existing;
        }
        $this->transaction(function () use ($id, $caseId, $mimeType, $sha256, $purpose, $privacyClass, $size, $context, $idempotencyKey, $at): void {
            $ok = $this->wpdb->insert($this->tables['attachments'], [
                'attachment_uuid' => $id, 'case_uuid' => $caseId->value(), 'provider_ref' => null,
                'sha256' => $sha256, 'mime_type' => $mimeType, 'purpose' => $purpose,
                'privacy_class' => $privacyClass, 'state' => 'uploaded', 'redacted_ref' => null,
                'expires_at' => null, 'record_version' => 1, 'created_at' => $this->mysqlTime($at),
            ]);
            if ($ok !== 1) {
                throw new RuntimeException('Attachment metadata persistence failed.');
            }
            $transitioned = $this->wpdb->update($this->tables['attachments'],
                ['state' => 'quarantined', 'record_version' => 2],
                ['attachment_uuid' => $id, 'state' => 'uploaded', 'record_version' => 1]
            );
            if ($transitioned !== 1) {
                throw new RuntimeException('Attachment quarantine transition failed.');
            }
            $this->appendEvent('attachment', $id, 'SupportAttachmentQuarantined', $context, $purpose, $idempotencyKey, [
                'case_ref' => $caseId->value(), 'privacy_class' => $privacyClass, 'state' => 'quarantined', 'size' => $size,
            ], 2, $at);
        });
        return $this->row($this->wpdb->prepare("SELECT * FROM {$this->tables['attachments']} WHERE attachment_uuid=%s", $id))
            ?? throw new RuntimeException('Attachment could not be reloaded.');
    }

    /** @return array<string,mixed> */
    public function recordAttachmentScan(
        string $attachmentId,
        string $providerRef,
        string $verdict,
        string $observedHash,
        string $scannerVersion,
        string $idempotencyKey,
        DateTimeImmutable $at
    ): array {
        $existing = $this->row($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['attachments']} WHERE attachment_uuid=%s LIMIT 1", $attachmentId
        ));
        if ($existing === null) {
            throw new RuntimeException('Attachment is not available for scan reconciliation.');
        }
        if (!hash_equals((string) $existing['sha256'], $observedHash)
            || !in_array($verdict, ['clean','infected','mime_mismatch','error'], true)
            || preg_match('/^[A-Za-z0-9._-]{1,64}$/', $scannerVersion) !== 1
            || trim($providerRef) === '') {
            throw new RuntimeException('Attachment scan evidence is invalid.');
        }
        $state = $verdict === 'clean' ? 'available' : 'rejected';
        $system = $this->systemContext($at);
        if (in_array((string) $existing['state'], ['available','rejected'], true)) {
            if (!hash_equals((string) $existing['state'], $state)
                || !hash_equals((string) ($existing['provider_ref'] ?? ''), $providerRef)) {
                throw new RuntimeException('Attachment scan replay differs from the recorded result.');
            }
            $this->appendEvent('attachment', $attachmentId, $state === 'rejected' ? 'SupportAttachmentRejected' : 'SupportAttachmentAvailable', $system, 'attachment_scan', $idempotencyKey, [
                'verdict' => $verdict, 'scanner_version' => $scannerVersion, 'provider_ref' => $providerRef,
            ], (int) $existing['record_version'], $at);
            return $existing;
        }
        if ((string) $existing['state'] !== 'quarantined') {
            throw new RuntimeException('Attachment is not available for scan reconciliation.');
        }
        $baseVersion = (int) $existing['record_version'];
        $this->transaction(function () use ($attachmentId, $providerRef, $state, $verdict, $scannerVersion, $idempotencyKey, $system, $at, $baseVersion): void {
            $scanned = $this->wpdb->update($this->tables['attachments'], [
                'provider_ref' => $providerRef, 'state' => 'scanned', 'record_version' => $baseVersion + 1,
            ], ['attachment_uuid' => $attachmentId, 'state' => 'quarantined', 'record_version' => $baseVersion]);
            if ($scanned !== 1) {
                throw new RuntimeException('Attachment scan transition conflicted.');
            }
            $finalized = $this->wpdb->update($this->tables['attachments'], [
                'state' => $state, 'record_version' => $baseVersion + 2,
                'expires_at' => $state === 'available' ? $this->mysqlTime($at->modify('+7 days')) : $this->mysqlTime($at->modify('+1 day')),
            ], ['attachment_uuid' => $attachmentId, 'state' => 'scanned', 'record_version' => $baseVersion + 1]);
            if ($finalized !== 1) {
                throw new RuntimeException('Attachment verdict transition conflicted.');
            }
            $this->appendEvent('attachment', $attachmentId, $state === 'rejected' ? 'SupportAttachmentRejected' : 'SupportAttachmentAvailable', $system, 'attachment_scan', $idempotencyKey, [
                'verdict' => $verdict, 'scanner_version' => $scannerVersion, 'provider_ref' => $providerRef,
            ], $baseVersion + 2, $at);
        });
        return $this->row($this->wpdb->prepare("SELECT * FROM {$this->tables['attachments']} WHERE attachment_uuid=%s", $attachmentId))
            ?? throw new RuntimeException('Attachment could not be reloaded.');
    }

    /** @return array<string,mixed> */
    public function recordAttachmentRedaction(string $attachmentId, string $redactedRef, string $idempotencyKey, DateTimeImmutable $at): array
    {
        $row = $this->row($this->wpdb->prepare("SELECT * FROM {$this->tables['attachments']} WHERE attachment_uuid=%s LIMIT 1", $attachmentId));
        if ($row === null || trim($redactedRef) === '') {
            throw new RuntimeException('Attachment is not eligible for redaction.');
        }
        $payload = ['redacted_ref_hash' => hash('sha256', $redactedRef), 'state' => 'redacted'];
        $system = $this->systemContext($at);
        if ((string) $row['state'] === 'redacted') {
            if (!hash_equals((string) ($row['redacted_ref'] ?? ''), $redactedRef)) {
                throw new RuntimeException('Attachment redaction replay differs from the recorded result.');
            }
            $this->appendEvent('attachment', $attachmentId, 'SupportAttachmentRedacted', $system, 'attachment_redaction', $idempotencyKey, $payload, (int) $row['record_version'], $at);
            return $row;
        }
        if ((string) $row['state'] !== 'available') {
            throw new RuntimeException('Attachment is not eligible for redaction.');
        }
        $version = (int) $row['record_version'];
        $this->transaction(function () use ($attachmentId, $redactedRef, $idempotencyKey, $payload, $system, $version, $at): void {
            $updated = $this->wpdb->update($this->tables['attachments'], [
                'state' => 'redacted', 'redacted_ref' => $redactedRef, 'record_version' => $version + 1,
            ], ['attachment_uuid' => $attachmentId, 'state' => 'available', 'record_version' => $version]);
            if ($updated !== 1) {
                throw new RuntimeException('Attachment redaction conflicted.');
            }
            $this->appendEvent('attachment', $attachmentId, 'SupportAttachmentRedacted', $system, 'attachment_redaction', $idempotencyKey, $payload, $version + 1, $at);
        });
        return $this->row($this->wpdb->prepare("SELECT * FROM {$this->tables['attachments']} WHERE attachment_uuid=%s", $attachmentId)) ?? [];
    }

    /** @return array<string,mixed> */
    public function assignCase(
        SupportCaseId $caseId,
        PrincipalContext $context,
        string $agentRef,
        string $queueKey,
        string $roleKey,
        array $scopes,
        string $reason,
        int $expectedVersion,
        string $idempotencyKey,
        DateTimeImmutable $at,
        bool $transfer
    ): array {
        $this->caseForActor($caseId, $context);
        if (trim($agentRef) === '' || trim($queueKey) === '' || trim($reason) === '' || $scopes === []) {
            throw new RuntimeException('Assignment metadata is incomplete.');
        }
        $assignmentPayload = ['agent_ref' => $agentRef, 'queue_key' => $queueKey, 'role_key' => $roleKey, 'transfer' => $transfer];
        if ($this->eventReplay($caseId->value(), 'SupportCaseAssigned', $idempotencyKey, $assignmentPayload)) {
            return $this->caseForActor($caseId, $context);
        }
        $this->transaction(function () use ($caseId, $context, $agentRef, $queueKey, $roleKey, $scopes, $reason, $expectedVersion, $idempotencyKey, $at, $transfer): void {
            $case = $this->row($this->wpdb->prepare(
                "SELECT record_version,owner_ref FROM {$this->tables['cases']} WHERE case_uuid=%s FOR UPDATE",
                $caseId->value()
            ));
            if ($case === null || (int) $case['record_version'] !== $expectedVersion) {
                throw new RuntimeException('Stale case version.');
            }
            if ($transfer) {
                $this->wpdb->update($this->tables['assignments'], [
                    'ended_at' => $this->mysqlTime($at),
                    'reason' => $reason,
                    'record_version' => 2,
                ], ['case_uuid' => $caseId->value(), 'ended_at' => null]);
            } elseif ($case['owner_ref'] !== null) {
                throw new RuntimeException('Case already has an accountable owner.');
            }
            $inserted = $this->wpdb->insert($this->tables['assignments'], [
                'case_uuid' => $caseId->value(), 'queue_key' => $queueKey, 'agent_ref' => $agentRef,
                'role_key' => $roleKey, 'scopes_json' => $this->json(array_values($scopes)),
                'started_at' => $this->mysqlTime($at), 'ended_at' => null, 'reason' => $reason, 'record_version' => 1,
            ]);
            if ($inserted !== 1) {
                throw new RuntimeException('Assignment persistence failed.');
            }
            $updated = $this->wpdb->update($this->tables['cases'], [
                'queue_key' => $queueKey, 'owner_ref' => $agentRef,
                'record_version' => $expectedVersion + 1, 'updated_at' => $this->mysqlTime($at),
            ], ['case_uuid' => $caseId->value(), 'record_version' => $expectedVersion]);
            if ($updated !== 1) {
                throw new RuntimeException('Case assignment update conflicted.');
            }
            $this->appendEvent('case', $caseId->value(), 'SupportCaseAssigned', $context, $transfer ? 'case_transfer' : 'case_assignment', $idempotencyKey, [
                'agent_ref' => $agentRef, 'queue_key' => $queueKey, 'role_key' => $roleKey, 'transfer' => $transfer,
            ], $expectedVersion + 1, $at);
        });
        return $this->caseForActor($caseId, $context);
    }

    /** @return array<string,mixed> */
    public function createTask(
        SupportCaseId $caseId,
        PrincipalContext $context,
        string $taskType,
        ?string $assigneeRef,
        ?string $dependencyRef,
        ?DateTimeImmutable $dueAt,
        string $idempotencyKey,
        string $purpose,
        DateTimeImmutable $at
    ): array {
        $this->caseForActor($caseId, $context);
        if (preg_match('/^[a-z][a-z0-9_]{2,63}$/', $taskType) !== 1 || ($dueAt !== null && $dueAt <= $at)) {
            throw new RuntimeException('Task metadata is invalid.');
        }
        $id = 'CF02-TASK-' . strtoupper(substr(hash('sha256', $caseId->value() . "\0" . $idempotencyKey), 0, 20));
        $existing = $this->row($this->wpdb->prepare("SELECT * FROM {$this->tables['tasks']} WHERE task_uuid=%s", $id));
        if ($existing !== null) {
            $same = hash_equals((string) $existing['case_uuid'], $caseId->value())
                && hash_equals((string) $existing['task_type'], $taskType)
                && hash_equals((string) ($existing['assignee_ref'] ?? ''), (string) ($assigneeRef ?? ''))
                && hash_equals((string) ($existing['dependency_ref'] ?? ''), (string) ($dependencyRef ?? ''));
            if (!$same) {
                throw new RuntimeException('Task idempotency collision.');
            }
            return $existing;
        }
        $this->transaction(function () use ($id, $caseId, $context, $taskType, $assigneeRef, $dependencyRef, $dueAt, $idempotencyKey, $purpose, $at): void {
            $ok = $this->wpdb->insert($this->tables['tasks'], [
                'task_uuid' => $id, 'case_uuid' => $caseId->value(), 'task_type' => $taskType,
                'assignee_ref' => $assigneeRef, 'dependency_ref' => $dependencyRef, 'state' => 'open',
                'outcome_ref' => null, 'due_at' => $dueAt ? $this->mysqlTime($dueAt) : null,
                'record_version' => 1, 'created_at' => $this->mysqlTime($at), 'updated_at' => $this->mysqlTime($at),
            ]);
            if ($ok !== 1) {
                throw new RuntimeException('Task persistence failed.');
            }
            $this->appendEvent('case', $caseId->value(), 'SupportTaskCreated', $context, $purpose, $idempotencyKey, [
                'task_ref' => $id, 'task_type' => $taskType, 'assignee_ref' => $assigneeRef, 'dependency_ref' => $dependencyRef,
            ], 1, $at);
        });
        return $this->row($this->wpdb->prepare("SELECT * FROM {$this->tables['tasks']} WHERE task_uuid=%s", $id)) ?? [];
    }

    /** @return array<string,mixed> */
    public function completeTask(string $taskId, PrincipalContext $context, int $expectedVersion, string $outcomeRef, string $idempotencyKey, string $purpose, DateTimeImmutable $at): array
    {
        $task = $this->row($this->wpdb->prepare("SELECT * FROM {$this->tables['tasks']} WHERE task_uuid=%s LIMIT 1", $taskId));
        if ($task === null) {
            throw new RuntimeException('Task not found.');
        }
        $caseId = SupportCaseId::fromString((string) $task['case_uuid']);
        $this->caseForActor($caseId, $context);
        $payload = ['task_ref' => $taskId, 'outcome_ref' => $outcomeRef];
        if ($this->eventReplay($caseId->value(), 'SupportTaskCompleted', $idempotencyKey, $payload)) {
            return $task;
        }
        if ($task['assignee_ref'] !== null && !hash_equals((string) $task['assignee_ref'], $context->actorReference()) && !$context->hasCapability('queue.manage')) {
            throw new RuntimeException('Task is assigned to another actor.');
        }
        if ((int) $task['record_version'] !== $expectedVersion || (string) $task['state'] !== 'open' || trim($outcomeRef) === '') {
            throw new RuntimeException('Task completion is invalid or stale.');
        }
        $this->transaction(function () use ($taskId, $caseId, $context, $expectedVersion, $outcomeRef, $idempotencyKey, $purpose, $payload, $at): void {
            $updated = $this->wpdb->update($this->tables['tasks'], [
                'state' => 'completed', 'outcome_ref' => $outcomeRef,
                'record_version' => $expectedVersion + 1, 'updated_at' => $this->mysqlTime($at),
            ], ['task_uuid' => $taskId, 'record_version' => $expectedVersion, 'state' => 'open']);
            if ($updated !== 1) {
                throw new RuntimeException('Task completion conflicted.');
            }
            $this->appendEvent('case', $caseId->value(), 'SupportTaskCompleted', $context, $purpose, $idempotencyKey, $payload, $expectedVersion + 1, $at);
        });
        return $this->row($this->wpdb->prepare("SELECT * FROM {$this->tables['tasks']} WHERE task_uuid=%s", $taskId)) ?? [];
    }

    /** @return array<string,mixed> */
    public function submitAppeal(
        SupportCaseId $caseId,
        PrincipalContext $context,
        string $originalDecisionRef,
        string $policyVersion,
        array $evidenceRefs,
        string $grounds,
        string $idempotencyKey,
        DateTimeImmutable $at
    ): array {
        $case = $this->caseForActor($caseId, $context, false);
        if (!in_array((string) $case['state'], ['resolved','closed'], true)) {
            throw new RuntimeException('Only a governed decision may be appealed.');
        }
        if (trim($originalDecisionRef) === '' || trim($policyVersion) === '' || trim($grounds) === '') {
            throw new RuntimeException('Appeal submission is incomplete.');
        }
        $appealId = 'CF02-APL-' . strtoupper(substr(hash('sha256', $caseId->value() . "\0" . $context->actorReference() . "\0" . $idempotencyKey), 0, 20));
        $existing = $this->row($this->wpdb->prepare("SELECT * FROM {$this->tables['appeals']} WHERE appeal_uuid=%s", $appealId));
        if ($existing !== null) {
            $dossier = $this->row($this->wpdb->prepare(
                "SELECT original_decision_ref,policy_version,evidence_refs_json,submissions_json FROM {$this->tables['dossiers']} WHERE appeal_uuid=%s LIMIT 1",
                $appealId
            ));
            $storedEvidence = is_array($dossier) ? json_decode((string) $dossier['evidence_refs_json'], true) : null;
            $storedSubmissions = is_array($dossier) ? json_decode((string) $dossier['submissions_json'], true) : null;
            $storedGrounds = is_array($storedSubmissions) && isset($storedSubmissions[0]['grounds']) ? (string) $storedSubmissions[0]['grounds'] : '';
            if ($dossier === null
                || !hash_equals((string) $existing['original_decision_ref'], $originalDecisionRef)
                || !hash_equals((string) $dossier['policy_version'], $policyVersion)
                || !hash_equals($storedGrounds, $grounds)
                || $storedEvidence !== array_values($evidenceRefs)) {
                throw new RuntimeException('Appeal idempotency collision.');
            }
            return $existing;
        }
        $originalHash = hash('sha256', $originalDecisionRef . "\0" . $policyVersion);
        $submissions = [['actor_ref' => $context->actorReference(), 'grounds' => $grounds, 'at' => $at->format(DATE_ATOM)]];
        $dossierHash = hash('sha256', $this->json([$originalHash, $policyVersion, $evidenceRefs, $submissions]));
        $this->transaction(function () use ($appealId, $caseId, $context, $originalDecisionRef, $policyVersion, $evidenceRefs, $submissions, $originalHash, $dossierHash, $idempotencyKey, $at): void {
            $ok = $this->wpdb->insert($this->tables['appeals'], [
                'appeal_uuid' => $appealId, 'case_uuid' => $caseId->value(),
                'appellant_ref' => $context->actorReference(), 'original_decision_ref' => $originalDecisionRef,
                'dossier_hash' => $dossierHash, 'reviewer_ref' => null, 'state' => 'submitted',
                'outcome' => null, 'native_command_ref' => null, 'implementation_ref' => null,
                'record_version' => 1, 'submitted_at' => $this->mysqlTime($at), 'updated_at' => $this->mysqlTime($at),
            ]);
            if ($ok !== 1) {
                throw new RuntimeException('Appeal persistence failed.');
            }
            $ok = $this->wpdb->insert($this->tables['dossiers'], [
                'dossier_uuid' => 'CF02-DOS-' . substr($appealId, -20), 'appeal_uuid' => $appealId,
                'original_decision_ref' => $originalDecisionRef, 'original_decision_hash' => $originalHash,
                'policy_version' => $policyVersion, 'evidence_refs_json' => $this->json(array_values($evidenceRefs)),
                'submissions_json' => $this->json($submissions), 'dossier_hash' => $dossierHash,
                'record_version' => 1, 'created_at' => $this->mysqlTime($at), 'updated_at' => $this->mysqlTime($at),
            ]);
            if ($ok !== 1) {
                throw new RuntimeException('Appeal dossier persistence failed.');
            }
            $this->appendEvent('appeal', $appealId, 'AppealSubmitted', $context, 'appeal_submission', $idempotencyKey, [
                'case_ref' => $caseId->value(), 'original_decision_ref' => $originalDecisionRef,
            ], 1, $at);
        });
        return $this->appealForActor($appealId, $context);
    }

    /** @return array<string,mixed> */
    public function mutateAppeal(
        string $appealId,
        PrincipalContext $context,
        int $expectedVersion,
        array $fields,
        string $event,
        string $purpose,
        string $idempotencyKey,
        array $payload,
        DateTimeImmutable $at
    ): array {
        SupportContractCatalog::assertEvent($event);
        $this->appealForActor($appealId, $context);
        if ($this->eventReplay($appealId, $event, $idempotencyKey, $payload)) {
            return $this->appealForActor($appealId, $context);
        }
        $allowed = ['state','reviewer_ref','outcome','native_command_ref','implementation_ref'];
        foreach (array_keys($fields) as $field) {
            if (!in_array($field, $allowed, true)) {
                throw new RuntimeException('Unsupported appeal mutation field.');
            }
        }
        $this->transaction(function () use ($appealId, $context, $expectedVersion, $fields, $event, $purpose, $idempotencyKey, $payload, $at): void {
            $data = $fields;
            $data['record_version'] = $expectedVersion + 1;
            $data['updated_at'] = $this->mysqlTime($at);
            $updated = $this->wpdb->update($this->tables['appeals'], $data, [
                'appeal_uuid' => $appealId, 'record_version' => $expectedVersion,
            ]);
            if ($updated !== 1) {
                throw new RuntimeException('Stale appeal version or mutation failure.');
            }
            $this->appendEvent('appeal', $appealId, $event, $context, $purpose, $idempotencyKey, $payload, $expectedVersion + 1, $at);
        });
        return $this->appealForActor($appealId, $context);
    }

    /** @return array<string,mixed> */
    public function createNativeCommand(
        SupportCaseId $caseId,
        PrincipalContext $context,
        string $nativeOwner,
        string $action,
        string $objectRef,
        int $expectedNativeVersion,
        array $payload,
        string $payloadCiphertext,
        string $idempotencyKey,
        string $purpose,
        DateTimeImmutable $at
    ): array {
        $this->caseForActor($caseId, $context);
        try {
            SupportContractCatalog::assertNativeOwnerKey($nativeOwner);
        } catch (\InvalidArgumentException) {
            throw new RuntimeException('Native-owner command metadata is invalid.');
        }
        if (trim($action) === '' || trim($objectRef) === '' || $expectedNativeVersion < 1) {
            throw new RuntimeException('Native-owner command metadata is invalid.');
        }
        $payloadHash = hash('sha256', $this->json($payload));
        $existing = $this->row($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['commands']} WHERE idempotency_key=%s LIMIT 1",
            $idempotencyKey
        ));
        if ($existing !== null) {
            if (!hash_equals((string) $existing['native_owner'], $nativeOwner)
                || !hash_equals((string) $existing['action_key'], $action)
                || !hash_equals((string) $existing['object_ref'], $objectRef)
                || !hash_equals((string) $existing['payload_hash'], $payloadHash)) {
                throw new RuntimeException('Native command idempotency collision.');
            }
            return $existing;
        }
        if (trim($payloadCiphertext) === '') {
            throw new RuntimeException('Encrypted native command payload is required.');
        }
        $id = 'CF02-CMD-' . strtoupper(substr(hash('sha256', $idempotencyKey), 0, 20));
        $this->transaction(function () use ($id, $caseId, $nativeOwner, $action, $objectRef, $expectedNativeVersion, $idempotencyKey, $payloadHash, $payloadCiphertext, $context, $purpose, $at): void {
            $ok = $this->wpdb->insert($this->tables['commands'], [
                'command_uuid' => $id, 'case_uuid' => $caseId->value(), 'native_owner' => $nativeOwner,
                'action_key' => $action, 'object_ref' => $objectRef, 'expected_native_version' => $expectedNativeVersion,
                'idempotency_key' => $idempotencyKey, 'payload_hash' => $payloadHash, 'state' => 'pending',
                'attempts' => 0, 'next_attempt_at' => $this->mysqlTime($at), 'outcome_ref' => null,
                'record_version' => 1, 'created_at' => $this->mysqlTime($at), 'updated_at' => $this->mysqlTime($at),
            ]);
            if ($ok !== 1) {
                throw new RuntimeException('Native command persistence failed.');
            }
            $payloadOk = $this->wpdb->insert($this->tables['command_payloads'], [
                'command_uuid' => $id, 'payload_ciphertext' => $payloadCiphertext,
                'payload_hash' => $payloadHash, 'created_at' => $this->mysqlTime($at),
            ]);
            if ($payloadOk !== 1) {
                throw new RuntimeException('Native command payload persistence failed.');
            }
            $this->appendEvent('case', $caseId->value(), 'SupportNativeCommandRequested', $context, $purpose, $idempotencyKey, [
                'command_ref' => $id, 'native_owner' => $nativeOwner, 'action' => $action, 'object_ref' => $objectRef,
            ], 1, $at);
        });
        return $this->row($this->wpdb->prepare("SELECT * FROM {$this->tables['commands']} WHERE command_uuid=%s", $id)) ?? [];
    }

    /** @return array<string,mixed> */
    public function applyHold(SupportCaseId $caseId, PrincipalContext $context, string $reason, string $authorityRef, DateTimeImmutable $reviewDue, string $idempotencyKey, DateTimeImmutable $at): array
    {
        $this->caseForActor($caseId, $context);
        if ($reviewDue <= $at || $reviewDue > $at->modify('+1 year') || trim($reason) === '' || trim($authorityRef) === '') {
            throw new RuntimeException('Hold evidence or review date is invalid.');
        }
        $id = 'CF02-HOLD-' . strtoupper(substr(hash('sha256', $caseId->value() . "\0" . $idempotencyKey), 0, 20));
        $existing = $this->row($this->wpdb->prepare("SELECT * FROM {$this->tables['holds']} WHERE hold_uuid=%s LIMIT 1", $id));
        if ($existing !== null) {
            if (!hash_equals((string) $existing['case_uuid'], $caseId->value())
                || !hash_equals((string) $existing['reason_code'], $reason)
                || !hash_equals((string) $existing['authority_ref'], $authorityRef)) {
                throw new RuntimeException('Hold idempotency collision.');
            }
            return $existing;
        }
        $this->transaction(function () use ($id, $caseId, $context, $reason, $authorityRef, $reviewDue, $idempotencyKey, $at): void {
            $ok = $this->wpdb->insert($this->tables['holds'], [
                'hold_uuid' => $id, 'case_uuid' => $caseId->value(), 'category' => null,
                'reason_code' => $reason, 'authority_ref' => $authorityRef, 'state' => 'active',
                'review_due_at' => $this->mysqlTime($reviewDue), 'placed_at' => $this->mysqlTime($at),
                'released_at' => null, 'record_version' => 1,
            ]);
            if ($ok !== 1) {
                throw new RuntimeException('Hold persistence failed.');
            }
            $this->appendEvent('case', $caseId->value(), 'SupportCaseHoldApplied', $context, 'case_hold', $idempotencyKey, [
                'hold_ref' => $id, 'reason_code' => $reason, 'authority_ref' => $authorityRef, 'review_due_at' => $reviewDue->format(DATE_ATOM),
            ], 1, $at);
        });
        return $this->row($this->wpdb->prepare("SELECT * FROM {$this->tables['holds']} WHERE hold_uuid=%s", $id)) ?? [];
    }

    /** @return array<string,mixed> */
    public function releaseHold(SupportCaseId $caseId, string $holdId, PrincipalContext $context, int $expectedVersion, string $reason, string $idempotencyKey, DateTimeImmutable $at): array
    {
        $this->caseForActor($caseId, $context);
        if (trim($reason) === '') {
            throw new RuntimeException('Hold release reason is required.');
        }
        $payload = ['hold_ref' => $holdId, 'reason' => $reason];
        if ($this->eventReplay($caseId->value(), 'SupportCaseHoldReleased', $idempotencyKey, $payload)) {
            return $this->row($this->wpdb->prepare("SELECT * FROM {$this->tables['holds']} WHERE hold_uuid=%s", $holdId)) ?? [];
        }
        $this->transaction(function () use ($caseId, $holdId, $context, $expectedVersion, $idempotencyKey, $payload, $at): void {
            $updated = $this->wpdb->update($this->tables['holds'], [
                'state' => 'released', 'released_at' => $this->mysqlTime($at), 'record_version' => $expectedVersion + 1,
            ], ['hold_uuid' => $holdId, 'case_uuid' => $caseId->value(), 'state' => 'active', 'record_version' => $expectedVersion]);
            if ($updated !== 1) {
                throw new RuntimeException('Hold release is invalid or stale.');
            }
            $this->appendEvent('case', $caseId->value(), 'SupportCaseHoldReleased', $context, 'case_hold_release', $idempotencyKey, $payload, $expectedVersion + 1, $at);
        });
        return $this->row($this->wpdb->prepare("SELECT * FROM {$this->tables['holds']} WHERE hold_uuid=%s", $holdId)) ?? [];
    }

    /** @return array<string,mixed> */
    public function stageConfiguration(PrincipalContext $context, string $key, array $config, array $approvals, string $idempotencyKey, DateTimeImmutable $at): array
    {
        if (preg_match('/^[a-z][a-z0-9_]{2,63}$/', $key) !== 1 || $config === []) {
            throw new RuntimeException('Configuration payload is invalid.');
        }
        $json = $this->json($config);
        $checksum = hash('sha256', $json);
        $existing = $this->row($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['configuration']} WHERE config_key=%s AND checksum=%s ORDER BY config_version DESC LIMIT 1", $key, $checksum
        ));
        if ($existing !== null) {
            return $existing;
        }
        $version = 1 + (int) $this->value($this->wpdb->prepare(
            "SELECT COALESCE(MAX(config_version),0) FROM {$this->tables['configuration']} WHERE config_key=%s", $key
        ));
        $ok = $this->wpdb->insert($this->tables['configuration'], [
            'config_key' => $key, 'config_version' => $version, 'status' => 'staged',
            'config_json' => $json, 'checksum' => $checksum,
            'approvals_json' => $this->json(array_values($approvals)), 'created_by' => $context->actorReference(),
            'created_at' => $this->mysqlTime($at),
        ]);
        if ($ok !== 1) {
            $replayed = $this->row($this->wpdb->prepare(
                "SELECT * FROM {$this->tables['configuration']} WHERE config_key=%s AND checksum=%s ORDER BY config_version DESC LIMIT 1", $key, $checksum
            ));
            if ($replayed !== null) {
                return $replayed;
            }
            throw new RuntimeException('Configuration staging failed or conflicted.');
        }
        $this->appendEvent('configuration', $key, 'SupportConfigurationStaged', $context, 'configuration_stage', $idempotencyKey, [
            'config_key' => $key, 'version' => $version, 'checksum' => $checksum,
        ], $version, $at);
        return $this->row($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['configuration']} WHERE config_key=%s AND config_version=%d", $key, $version
        )) ?? [];
    }

    /** @return array<string,mixed> */
    public function activateConfiguration(string $key, int $version, PrincipalContext $context, string $approvalRef, string $idempotencyKey, DateTimeImmutable $at): array
    {
        $row = $this->row($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['configuration']} WHERE config_key=%s AND config_version=%d LIMIT 1", $key, $version
        ));
        if ($row === null || !in_array((string) $row['status'], ['staged','active'], true) || trim($approvalRef) === '') {
            throw new RuntimeException('Configuration is not eligible for activation.');
        }
        if ((string) $row['status'] === 'active') {
            return $row;
        }
        $approvals = json_decode((string) $row['approvals_json'], true);
        $approved = is_array($approvals) ? array_values(array_unique(array_filter($approvals, 'is_string'))) : [];
        if (count($approved) < 2 || !in_array($approvalRef, $approved, true) || hash_equals((string) $row['created_by'], $context->actorReference())) {
            throw new RuntimeException('Activation requires two independent approvals and separation of duties.');
        }
        $this->transaction(function () use ($key, $version, $context, $approvalRef, $idempotencyKey, $at): void {
            $this->wpdb->query($this->wpdb->prepare(
                "UPDATE {$this->tables['configuration']} SET status='superseded' WHERE config_key=%s AND status='active'", $key
            ));
            $updated = $this->wpdb->update($this->tables['configuration'], ['status' => 'active'], [
                'config_key' => $key, 'config_version' => $version, 'status' => 'staged',
            ]);
            if ($updated !== 1) {
                throw new RuntimeException('Configuration activation conflicted.');
            }
            $this->appendEvent('configuration', $key, 'SupportConfigurationActivated', $context, 'configuration_activation', $idempotencyKey, [
                'config_key' => $key, 'version' => $version, 'approval_ref' => $approvalRef,
            ], $version, $at);
        });
        return $this->row($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['configuration']} WHERE config_key=%s AND config_version=%d", $key, $version
        )) ?? [];
    }

    /** @return list<array<string,mixed>> */
    public function appealQueue(PrincipalContext $context, int $limit, int $offset): array
    {
        $where = '1=1';
        $args = [];
        if (!$context->hasCapability('appeal.queue.read')) {
            $where = 'reviewer_ref=%s';
            $args[] = $context->actorReference();
        }
        $args[] = max(1, min(100, $limit));
        $args[] = max(0, $offset);
        return $this->rows($this->wpdb->prepare(
            "SELECT appeal_uuid,case_uuid,original_decision_ref,reviewer_ref,state,outcome,native_command_ref,implementation_ref,record_version,submitted_at,updated_at
             FROM {$this->tables['appeals']} WHERE {$where}
             ORDER BY FIELD(state,'submitted','eligibility_review','accepted','under_review','native_decision_pending','decided','implemented','reopened','rejected','closed'),updated_at ASC
             LIMIT %d OFFSET %d",
            ...$args
        ));
    }

    /** @return array<string,mixed> */
    public function rollbackConfiguration(string $key, int $version, PrincipalContext $context, string $approvalRef, string $idempotencyKey, DateTimeImmutable $at): array
    {
        $row = $this->row($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['configuration']} WHERE config_key=%s AND config_version=%d LIMIT 1", $key, $version
        ));
        if ($row === null || !in_array((string) $row['status'], ['superseded','active'], true) || trim($approvalRef) === '') {
            throw new RuntimeException('Configuration snapshot is not eligible for rollback.');
        }
        if ((string) $row['status'] === 'active') {
            return $row;
        }
        $approvals = json_decode((string) $row['approvals_json'], true);
        $approved = is_array($approvals) ? array_values(array_unique(array_filter($approvals, 'is_string'))) : [];
        if (count($approved) < 2 || !in_array($approvalRef, $approved, true)) {
            throw new RuntimeException('Rollback requires the approved snapshot evidence.');
        }
        $this->transaction(function () use ($key, $version, $context, $approvalRef, $idempotencyKey, $at): void {
            $this->wpdb->query($this->wpdb->prepare(
                "UPDATE {$this->tables['configuration']} SET status='superseded' WHERE config_key=%s AND status='active' AND config_version<>%d", $key, $version
            ));
            $updated = $this->wpdb->update($this->tables['configuration'], ['status' => 'active'], [
                'config_key' => $key, 'config_version' => $version, 'status' => 'superseded',
            ]);
            if ($updated !== 1) {
                throw new RuntimeException('Configuration rollback conflicted.');
            }
            $this->appendEvent('configuration', $key, 'SupportConfigurationRolledBack', $context, 'configuration_rollback', $idempotencyKey, [
                'config_key' => $key, 'version' => $version, 'approval_ref' => $approvalRef,
            ], $version, $at);
        });
        return $this->row($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['configuration']} WHERE config_key=%s AND config_version=%d", $key, $version
        )) ?? [];
    }

    /** @return array<string,mixed> */
    public function backlogHealth(): array
    {
        $rows = $this->rows(
            "SELECT queue_key,priority,state,COUNT(*) AS total,MIN(created_at) AS oldest_created_at
             FROM {$this->tables['cases']} WHERE state NOT IN ('closed')
             GROUP BY queue_key,priority,state ORDER BY queue_key,FIELD(priority,'P1','P2','P3','P4'),state"
        );
        return ['generated_at' => gmdate(DATE_ATOM), 'groups' => $rows, 'privacy_safe' => true];
    }

    public function purgeCase(string $caseId, array $providerResults, DateTimeImmutable $at): void
    {
        $activeHolds = (int) $this->value($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tables['holds']} WHERE case_uuid=%s AND state='active'", $caseId
        ));
        if ($activeHolds > 0) {
            throw new RuntimeException('Active legal or appeal hold blocks purge.');
        }
        if (($providerResults['all_targets_reconciled'] ?? false) !== true) {
            throw new RuntimeException('Provider/cache/search deletion reconciliation is incomplete.');
        }
        $this->transaction(function () use ($caseId): void {
            $appeals = $this->rows($this->wpdb->prepare("SELECT appeal_uuid FROM {$this->tables['appeals']} WHERE case_uuid=%s", $caseId));
            foreach ($appeals as $appeal) {
                $this->wpdb->delete($this->tables['dossiers'], ['appeal_uuid' => (string) $appeal['appeal_uuid']]);
            }
            foreach (['messages','attachments','assignments','sla','tasks','appeals','commands','outbox','case_links','incident_links','feedback'] as $table) {
                $this->wpdb->delete($this->tables[$table], ['case_uuid' => $caseId]);
            }
            $this->wpdb->query("DELETE p FROM {$this->tables['command_payloads']} p LEFT JOIN {$this->tables['commands']} c ON c.command_uuid=p.command_uuid WHERE c.command_uuid IS NULL");
            $this->wpdb->query("DELETE p FROM {$this->tables['outbox_payloads']} p LEFT JOIN {$this->tables['outbox']} o ON o.message_uuid=p.message_uuid WHERE o.message_uuid IS NULL");
            $this->wpdb->query($this->wpdb->prepare("DELETE FROM {$this->tables['merge_redirects']} WHERE source_case_uuid=%s OR target_case_uuid=%s", $caseId, $caseId));
            $deleted = $this->wpdb->delete($this->tables['cases'], ['case_uuid' => $caseId]);
            if ($deleted !== 1) {
                throw new RuntimeException('Canonical case purge failed.');
            }
        });
        $system = $this->systemContext($at);
        $this->appendEvent('case', $caseId, 'SupportRetentionPurgeCompleted', $system, 'retention_purge',
            'retention-purge-' . substr(hash('sha256', $caseId . "\0" . $this->json($providerResults)), 0, 40),
            ['provider_results_hash' => hash('sha256', $this->json($providerResults))], 0, $at);
    }

    /** @return list<array<string,mixed>> */
    public function dueRetention(int $limit = 250): array
    {
        $limit = max(1, min(1000, $limit));
        $config = $this->row("SELECT config_json FROM {$this->tables['configuration']} WHERE config_key='retention_schedule' AND status='active' ORDER BY config_version DESC LIMIT 1");
        if ($config === null) {
            return [];
        }
        $schedule = json_decode((string) $config['config_json'], true);
        if (!is_array($schedule) || !isset($schedule['default_days']) || !is_int($schedule['default_days']) || $schedule['default_days'] < 1) {
            return [];
        }
        $rows = $this->rows($this->wpdb->prepare(
            "SELECT case_uuid,category,state,closed_at,updated_at FROM {$this->tables['cases']} WHERE state='closed' AND closed_at IS NOT NULL ORDER BY closed_at ASC LIMIT %d",
            min(1000, $limit * 4)
        ));
        $due = [];
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $categoryDays = is_array($schedule['category_days'] ?? null) ? $schedule['category_days'] : [];
        foreach ($rows as $row) {
            $days = $categoryDays[(string) $row['category']] ?? $schedule['default_days'];
            if (!is_int($days) || $days < 1 || $days > 3650) {
                continue;
            }
            $closed = new DateTimeImmutable((string) $row['closed_at'], new DateTimeZone('UTC'));
            if ($closed->modify('+' . $days . ' days') <= $now) {
                $due[] = $row;
                if (count($due) >= $limit) {
                    break;
                }
            }
        }
        return $due;
    }

    /** @return list<array<string,mixed>> */
    public function pendingEvents(int $limit): array
    {
        return $this->rows($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['events']} WHERE publish_state IN ('pending','retry')
             AND (next_attempt_at IS NULL OR next_attempt_at<=UTC_TIMESTAMP(6)) ORDER BY id ASC LIMIT %d",
            max(1, min(250, $limit))
        ));
    }

    public function markEventPublished(string $eventId): void
    {
        $this->wpdb->update($this->tables['events'], ['publish_state' => 'published'], ['event_uuid' => $eventId]);
    }

    public function markEventRetry(string $eventId, int $attempts, DateTimeImmutable $next): void
    {
        $this->wpdb->update($this->tables['events'], [
            'publish_state' => $attempts >= 8 ? 'dead_letter' : 'retry',
            'publish_attempts' => $attempts,
            'next_attempt_at' => $this->mysqlTime($next),
        ], ['event_uuid' => $eventId]);
    }

    /** @return array<string,mixed>|null */
    public function command(string $commandId): ?array
    {
        return $this->row($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['commands']} WHERE command_uuid=%s LIMIT 1",
            $commandId
        ));
    }

    public function enqueueDelivery(
        SupportCaseId $caseId,
        string $recipientRef,
        string $channel,
        string $templateKey,
        array $payload,
        string $payloadCiphertext,
        string $idempotencyKey,
        DateTimeImmutable $at
    ): string {
        if (!in_array($channel, ['in_app','email','browser','sms','chat'], true)
            || trim($recipientRef) === '' || trim($templateKey) === '' || trim($payloadCiphertext) === '') {
            throw new RuntimeException('Delivery request metadata is invalid.');
        }
        $payloadHash = hash('sha256', $this->json($payload));
        $messageId = 'CF02-OUT-' . strtoupper(substr(hash('sha256', $idempotencyKey), 0, 20));
        $existing = $this->row($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['outbox']} WHERE idempotency_key=%s LIMIT 1",
            $idempotencyKey
        ));
        if ($existing !== null) {
            if (!hash_equals((string) $existing['payload_hash'], $payloadHash)
                || !hash_equals((string) $existing['recipient_ref'], $recipientRef)
                || !hash_equals((string) $existing['channel'], $channel)) {
                throw new RuntimeException('Delivery idempotency collision.');
            }
            return (string) $existing['message_uuid'];
        }
        $this->transaction(function () use ($messageId, $caseId, $recipientRef, $channel, $templateKey, $payloadHash, $payloadCiphertext, $idempotencyKey, $at): void {
            $ok = $this->wpdb->insert($this->tables['outbox'], [
                'message_uuid' => $messageId, 'case_uuid' => $caseId->value(), 'channel' => $channel,
                'recipient_ref' => $recipientRef, 'template_key' => $templateKey, 'payload_hash' => $payloadHash,
                'idempotency_key' => $idempotencyKey, 'state' => 'pending', 'attempts' => 0,
                'next_attempt_at' => $this->mysqlTime($at), 'provider_ref' => null,
                'created_at' => $this->mysqlTime($at), 'updated_at' => $this->mysqlTime($at),
            ]);
            if ($ok !== 1) {
                throw new RuntimeException('Delivery outbox persistence failed.');
            }
            $payloadOk = $this->wpdb->insert($this->tables['outbox_payloads'], [
                'message_uuid' => $messageId, 'payload_ciphertext' => $payloadCiphertext,
                'payload_hash' => $payloadHash, 'created_at' => $this->mysqlTime($at),
            ]);
            if ($payloadOk !== 1) {
                throw new RuntimeException('Delivery payload persistence failed.');
            }
        });
        return $messageId;
    }

    /** @return list<array<string,mixed>> */
    public function pendingOutbox(int $limit): array
    {
        return $this->rows($this->wpdb->prepare(
            "SELECT o.*,p.payload_ciphertext FROM {$this->tables['outbox']} o JOIN {$this->tables['outbox_payloads']} p ON p.message_uuid=o.message_uuid WHERE o.state IN ('pending','retry')
             AND (o.next_attempt_at IS NULL OR o.next_attempt_at<=UTC_TIMESTAMP(6)) ORDER BY o.id ASC LIMIT %d",
            max(1, min(250, $limit))
        ));
    }

    public function updateOutboxResult(string $messageId, string $state, int $attempts, ?string $providerRef, ?DateTimeImmutable $next, DateTimeImmutable $at): void
    {
        if (!in_array($state, ['sent','retry','dead_letter'], true)) {
            throw new RuntimeException('Invalid delivery result state.');
        }
        $updated = $this->wpdb->update($this->tables['outbox'], [
            'state' => $state, 'attempts' => $attempts, 'provider_ref' => $providerRef,
            'next_attempt_at' => $next ? $this->mysqlTime($next) : null, 'updated_at' => $this->mysqlTime($at),
        ], ['message_uuid' => $messageId]);
        if ($updated !== 1) {
            throw new RuntimeException('Delivery outbox update failed.');
        }
    }

    /** @return array{token:string,expires_at:string} */
    public function issueAttachmentToken(string $attachmentId, SupportCaseId $caseId, PrincipalContext $context, string $purpose, DateTimeImmutable $at): array
    {
        $this->caseForActor($caseId, $context);
        $attachment = $this->row($this->wpdb->prepare(
            "SELECT attachment_uuid,case_uuid,state,expires_at FROM {$this->tables['attachments']} WHERE attachment_uuid=%s AND case_uuid=%s LIMIT 1",
            $attachmentId, $caseId->value()
        ));
        if ($attachment === null || !in_array((string) $attachment['state'], ['available','redacted'], true)) {
            throw new RuntimeException('Attachment is not available.');
        }
        $expires = $at->modify('+5 minutes');
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $ok = $this->wpdb->insert($this->tables['tokens'], [
            'token_hash' => hash('sha256', $token), 'attachment_uuid' => $attachmentId,
            'actor_ref' => $context->actorReference(), 'purpose' => $purpose,
            'expires_at' => $this->mysqlTime($expires), 'used_at' => null, 'created_at' => $this->mysqlTime($at),
        ]);
        if ($ok !== 1) {
            throw new RuntimeException('Attachment token could not be issued.');
        }
        return ['token' => $token, 'expires_at' => $expires->format(DATE_ATOM)];
    }

    /** @return array<string,mixed> */
    public function consumeAttachmentToken(string $token, DateTimeImmutable $at): array
    {
        $hash = hash('sha256', $token);
        $row = $this->row($this->wpdb->prepare(
            "SELECT t.*,a.provider_ref,a.redacted_ref,a.state,a.case_uuid FROM {$this->tables['tokens']} t
             JOIN {$this->tables['attachments']} a ON a.attachment_uuid=t.attachment_uuid
             WHERE t.token_hash=%s AND t.used_at IS NULL AND t.expires_at>UTC_TIMESTAMP(6) LIMIT 1",
            $hash
        ));
        if ($row === null || !in_array((string) $row['state'], ['available','redacted'], true)) {
            throw new RuntimeException('Attachment token is invalid or expired.');
        }
        $updated = $this->wpdb->update($this->tables['tokens'], ['used_at' => $this->mysqlTime($at)], ['token_hash' => $hash, 'used_at' => null]);
        if ($updated !== 1) {
            throw new RuntimeException('Attachment token replay was rejected.');
        }
        return $row;
    }

    public function recordRetentionResult(string $objectType, string $objectRef, string $policyVersion, string $action, array $providerResults, DateTimeImmutable $at): void
    {
        $evidence = hash('sha256', $this->json([$objectType,$objectRef,$policyVersion,$action,$providerResults,$at->format(DATE_ATOM)]));
        $ok = $this->wpdb->insert($this->tables['retention'], [
            'object_type' => $objectType, 'object_ref' => $objectRef, 'policy_version' => $policyVersion,
            'action_key' => $action, 'provider_results_json' => $this->json($providerResults),
            'evidence_hash' => $evidence, 'executed_at' => $this->mysqlTime($at),
        ]);
        if ($ok !== 1) {
            throw new RuntimeException('Retention evidence persistence failed.');
        }
    }

    /** @return list<array<string,mixed>> */
    public function pendingCommands(int $limit): array
    {
        return $this->rows($this->wpdb->prepare(
            "SELECT c.*,p.payload_ciphertext FROM {$this->tables['commands']} c
             JOIN {$this->tables['command_payloads']} p ON p.command_uuid=c.command_uuid
             WHERE c.state IN ('pending','retry','outcome_uncertain')
             AND (c.next_attempt_at IS NULL OR c.next_attempt_at<=UTC_TIMESTAMP(6)) ORDER BY c.id ASC LIMIT %d",
            max(1, min(250, $limit))
        ));
    }

    public function updateCommandResult(string $commandId, string $state, ?string $outcomeRef, int $attempts, ?DateTimeImmutable $next, DateTimeImmutable $at): void
    {
        if (!in_array($state, ['succeeded','retry','failed','outcome_uncertain','dead_letter'], true)) {
            throw new RuntimeException('Invalid command result state.');
        }
        $row = $this->row($this->wpdb->prepare(
            "SELECT record_version FROM {$this->tables['commands']} WHERE command_uuid=%s LIMIT 1",
            $commandId
        ));
        if ($row === null) {
            throw new RuntimeException('Native command was not found.');
        }
        $updated = $this->wpdb->update($this->tables['commands'], [
            'state' => $state, 'outcome_ref' => $outcomeRef, 'attempts' => $attempts,
            'next_attempt_at' => $next ? $this->mysqlTime($next) : null,
            'record_version' => (int) $row['record_version'] + 1,
            'updated_at' => $this->mysqlTime($at),
        ], ['command_uuid' => $commandId, 'record_version' => (int) $row['record_version']]);
        if ($updated !== 1) {
            throw new RuntimeException('Native command result update conflicted.');
        }
    }

    /** @return array<string,mixed>|null */
    public function inboundReceipt(string $sourceOwner, string $externalEventId): ?array
    {
        return $this->row($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['inbound']} WHERE source_owner=%s AND external_event_id=%s LIMIT 1",
            $sourceOwner, $externalEventId
        ));
    }

    public function recordInboundReceipt(
        string $sourceOwner,
        string $externalEventId,
        string $channel,
        string $senderRef,
        string $senderTrust,
        string $signatureHash,
        string $payloadHash,
        ?string $caseId,
        DateTimeImmutable $at
    ): string {
        $id = 'CF02-IN-' . strtoupper(substr(hash('sha256', $sourceOwner . "\0" . $externalEventId), 0, 20));
        $ok = $this->wpdb->insert($this->tables['inbound'], [
            'receipt_uuid' => $id, 'source_owner' => $sourceOwner, 'external_event_id' => $externalEventId,
            'channel' => $channel, 'sender_ref' => $senderRef, 'sender_trust' => $senderTrust,
            'signature_hash' => $signatureHash, 'payload_hash' => $payloadHash,
            'case_uuid' => $caseId, 'received_at' => $this->mysqlTime($at),
        ]);
        if ($ok !== 1) {
            throw new RuntimeException('Inbound receipt persistence failed.');
        }
        return $id;
    }

    /** @param array<string,mixed> $payload */
    public function appendEvent(
        string $aggregateType,
        string $aggregateRef,
        string $eventType,
        PrincipalContext $context,
        string $purpose,
        string $idempotencyKey,
        array $payload,
        int $objectVersion,
        DateTimeImmutable $at
    ): string {
        SupportContractCatalog::assertEvent($eventType);
        $payloadJson = $this->json($payload);
        $payloadHash = hash('sha256', $payloadJson);
        $previous = $this->value($this->wpdb->prepare(
            "SELECT event_hash FROM {$this->tables['events']} WHERE aggregate_type=%s AND aggregate_ref=%s ORDER BY id DESC LIMIT 1",
            $aggregateType, $aggregateRef
        ));
        $previousHash = is_string($previous) && $previous !== '' ? $previous : null;
        $eventId = 'CF02-EVT-' . strtoupper(substr(hash('sha256', $aggregateType . "\0" . $aggregateRef . "\0" . $eventType . "\0" . $idempotencyKey), 0, 20));
        $role = $context->roles()[0] ?? 'system';
        $eventHash = hash('sha256', $this->json([
            $eventId, $aggregateType, $aggregateRef, $eventType, $context->actorReference(), $role,
            $purpose, $payloadHash, $objectVersion, $previousHash, $at->format(DATE_ATOM),
        ]));
        $existing = $this->row($this->wpdb->prepare("SELECT payload_hash,event_hash FROM {$this->tables['events']} WHERE event_uuid=%s", $eventId));
        if ($existing !== null) {
            if (!hash_equals((string) $existing['payload_hash'], $payloadHash)) {
                throw new RuntimeException('Event idempotency collision.');
            }
            return $eventId;
        }
        $ok = $this->wpdb->insert($this->tables['events'], [
            'event_uuid' => $eventId, 'aggregate_type' => $aggregateType, 'aggregate_ref' => $aggregateRef,
            'event_type' => $eventType, 'actor_ref' => $context->actorReference(), 'actor_role' => $role,
            'purpose' => $purpose, 'payload_json' => $payloadJson, 'payload_hash' => $payloadHash,
            'idempotency_key' => $idempotencyKey, 'publish_state' => 'pending', 'publish_attempts' => 0,
            'next_attempt_at' => $this->mysqlTime($at), 'previous_hash' => $previousHash,
            'event_hash' => $eventHash, 'occurred_at' => $this->mysqlTime($at),
        ]);
        if ($ok !== 1) {
            throw new RuntimeException('Event persistence failed.');
        }
        $this->appendAudit($aggregateType, $aggregateRef, $context, $purpose, $eventType, 'accepted', $objectVersion, $payloadHash, $at);
        return $eventId;
    }

    private function appendAudit(
        string $objectType,
        string $objectRef,
        PrincipalContext $context,
        string $purpose,
        string $action,
        string $result,
        int $objectVersion,
        string $contextHash,
        DateTimeImmutable $at
    ): void {
        $previous = $this->value($this->wpdb->prepare(
            "SELECT event_hash FROM {$this->tables['audit']} WHERE object_type=%s AND object_ref=%s ORDER BY id DESC LIMIT 1",
            $objectType, $objectRef
        ));
        $previousHash = is_string($previous) && $previous !== '' ? $previous : null;
        $trace = RequestGuard::traceId();
        $eventId = 'CF02-AUD-' . strtoupper(bin2hex(random_bytes(10)));
        $eventHash = hash('sha256', $this->json([
            $eventId, $objectType, $objectRef, $context->actorReference(), $purpose, $action,
            $result, $trace, $objectVersion, $contextHash, $previousHash, $at->format(DATE_ATOM),
        ]));
        $ok = $this->wpdb->insert($this->tables['audit'], [
            'event_uuid' => $eventId, 'object_type' => $objectType, 'object_ref' => $objectRef,
            'actor_ref' => $context->actorReference(), 'purpose' => $purpose, 'action_key' => $action,
            'result_code' => $result, 'trace_id' => $trace, 'object_version' => $objectVersion,
            'context_hash' => $contextHash, 'previous_hash' => $previousHash, 'event_hash' => $eventHash,
            'occurred_at' => $this->mysqlTime($at),
        ]);
        if ($ok !== 1) {
            throw new RuntimeException('Audit evidence persistence failed.');
        }
    }

    /** @return array<string,mixed> */
    public function caseProjection(SupportCaseId $caseId, PrincipalContext $context): array
    {
        $case = $this->caseForActor($caseId, $context);
        $staff = !hash_equals((string) $case['requester_ref'], $context->actorReference())
            && !$context->represents((string) $case['requester_ref']);
        $visibility = $staff ? "visibility IN ('requester','internal','restricted')" : "visibility='requester'";
        if ($staff && !$context->hasAnyCapability('case.sensitive.read', 'case.specialist.read')) {
            $visibility = "visibility IN ('requester','internal')";
        }
        $messages = $this->rows($this->wpdb->prepare(
            "SELECT message_uuid,author_ref,visibility,channel,body_ciphertext,body_hash,record_version,created_at,edited_at
             FROM {$this->tables['messages']} WHERE case_uuid=%s AND {$visibility} ORDER BY id ASC LIMIT 500",
            $caseId->value()
        ));
        $attachments = $this->rows($this->wpdb->prepare(
            "SELECT attachment_uuid,sha256,mime_type,purpose,privacy_class,state,redacted_ref,expires_at,record_version,created_at
             FROM {$this->tables['attachments']} WHERE case_uuid=%s ORDER BY id ASC LIMIT 250",
            $caseId->value()
        ));
        if (!$staff) {
            $attachments = array_values(array_map(static function (array $row): array {
                unset($row['sha256'], $row['redacted_ref']);
                return $row;
            }, array_filter($attachments, static fn (array $row): bool => in_array((string) $row['state'], ['available','redacted','superseded','expired'], true))));
        }
        $result = ['case' => $case, 'messages' => $messages, 'attachments' => $attachments];
        if ($staff) {
            $result['assignments'] = $this->rows($this->wpdb->prepare(
                "SELECT queue_key,agent_ref,role_key,scopes_json,started_at,ended_at,reason,record_version
                 FROM {$this->tables['assignments']} WHERE case_uuid=%s ORDER BY id ASC",
                $caseId->value()
            ));
            $result['tasks'] = $this->rows($this->wpdb->prepare(
                "SELECT task_uuid,task_type,assignee_ref,dependency_ref,state,outcome_ref,due_at,record_version,created_at,updated_at
                 FROM {$this->tables['tasks']} WHERE case_uuid=%s ORDER BY id ASC",
                $caseId->value()
            ));
            $result['links'] = $this->rows($this->wpdb->prepare(
                "SELECT link_uuid,owner_key,object_type,object_ref,object_version,privacy_class,state,created_at,updated_at
                 FROM {$this->tables['case_links']} WHERE case_uuid=%s AND state='active' ORDER BY id ASC",
                $caseId->value()
            ));
            $result['holds'] = $this->rows($this->wpdb->prepare(
                "SELECT hold_uuid,reason_code,authority_ref,state,review_due_at,placed_at,released_at,record_version
                 FROM {$this->tables['holds']} WHERE case_uuid=%s ORDER BY id ASC",
                $caseId->value()
            ));
        }
        return $result;
    }

    /** @return array<string,mixed> */
    public function appealProjection(string $appealId, PrincipalContext $context): array
    {
        $appeal = $this->appealForActor($appealId, $context);
        $dossier = $this->row($this->wpdb->prepare(
            "SELECT dossier_uuid,appeal_uuid,original_decision_ref,original_decision_hash,policy_version,evidence_refs_json,submissions_json,dossier_hash,record_version,created_at,updated_at
             FROM {$this->tables['dossiers']} WHERE appeal_uuid=%s LIMIT 1",
            $appealId
        ));
        return ['appeal' => $appeal, 'dossier' => $dossier];
    }

    /** @return array<string,mixed> */
    public function addFeedback(
        SupportCaseId $caseId,
        PrincipalContext $context,
        ?int $rating,
        ?string $commentCiphertext,
        bool $optedOut,
        DateTimeImmutable $at
    ): array {
        $case = $this->caseForActor($caseId, $context, false);
        if (!in_array((string) $case['state'], ['resolved','closed'], true)) {
            throw new RuntimeException('Feedback is available only after a governed resolution.');
        }
        if (!$optedOut && ($rating === null || $rating < 1 || $rating > 5)) {
            throw new RuntimeException('Feedback rating is invalid.');
        }
        $pseudonym = hash('sha256', $context->actorReference() . "\0" . $caseId->value());
        $existing = $this->row($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['feedback']} WHERE case_uuid=%s AND respondent_pseudonym_hash=%s LIMIT 1",
            $caseId->value(), $pseudonym
        ));
        if ($existing !== null) {
            return $existing;
        }
        $ok = $this->wpdb->insert($this->tables['feedback'], [
            'case_uuid' => $caseId->value(), 'respondent_pseudonym_hash' => $pseudonym,
            'rating' => $optedOut ? null : $rating, 'comment_ciphertext' => $optedOut ? null : $commentCiphertext,
            'opted_out' => $optedOut ? 1 : 0, 'submitted_at' => $this->mysqlTime($at),
        ]);
        if ($ok !== 1) {
            throw new RuntimeException('Feedback persistence failed.');
        }
        return $this->row($this->wpdb->prepare(
            "SELECT case_uuid,rating,opted_out,submitted_at FROM {$this->tables['feedback']} WHERE case_uuid=%s AND respondent_pseudonym_hash=%s",
            $caseId->value(), $pseudonym
        )) ?? [];
    }

    /** @return array<string,mixed> */
    public function mergeCases(
        SupportCaseId $source,
        SupportCaseId $target,
        PrincipalContext $context,
        string $reason,
        string $idempotencyKey,
        DateTimeImmutable $at
    ): array {
        if ($source->equals($target)) {
            throw new RuntimeException('A case cannot be merged into itself.');
        }
        $sourceRow = $this->caseForActor($source, $context);
        $targetRow = $this->caseForActor($target, $context);
        if (!hash_equals((string) $sourceRow['requester_ref'], (string) $targetRow['requester_ref'])) {
            throw new RuntimeException('Cross-requester case merge is prohibited.');
        }
        if (trim($reason) === '') {
            throw new RuntimeException('Merge reason is required.');
        }
        $payload = ['source' => $source->value(), 'target' => $target->value(), 'reason' => $reason];
        if ($this->eventReplay($source->value(), 'SupportCasesMerged', $idempotencyKey, $payload)) {
            return $this->row($this->wpdb->prepare("SELECT * FROM {$this->tables['merge_redirects']} WHERE source_case_uuid=%s AND active=1 LIMIT 1", $source->value())) ?? [];
        }
        $existing = $this->row($this->wpdb->prepare(
            "SELECT * FROM {$this->tables['merge_redirects']} WHERE source_case_uuid=%s AND active=1 LIMIT 1", $source->value()
        ));
        if ($existing !== null) {
            if (!hash_equals((string) $existing['target_case_uuid'], $target->value())) {
                throw new RuntimeException('Source case is already redirected to another target.');
            }
            return $existing;
        }
        $this->transaction(function () use ($source, $target, $context, $reason, $idempotencyKey, $payload, $at): void {
            $ok = $this->wpdb->insert($this->tables['merge_redirects'], [
                'source_case_uuid' => $source->value(), 'target_case_uuid' => $target->value(),
                'reason' => $reason, 'actor_ref' => $context->actorReference(), 'active' => 1,
                'reversal_ref' => null, 'created_at' => $this->mysqlTime($at), 'reversed_at' => null,
            ]);
            if ($ok !== 1) {
                throw new RuntimeException('Merge redirect persistence failed.');
            }
            $version = (int) $this->value($this->wpdb->prepare("SELECT record_version FROM {$this->tables['cases']} WHERE case_uuid=%s", $source->value()));
            $this->appendEvent('case', $source->value(), 'SupportCasesMerged', $context, 'case_merge', $idempotencyKey, $payload, $version, $at);
        });
        return $this->row($this->wpdb->prepare("SELECT * FROM {$this->tables['merge_redirects']} WHERE source_case_uuid=%s AND active=1", $source->value())) ?? [];
    }

    /** @return array<string,mixed> */
    public function splitMergedCase(SupportCaseId $source, PrincipalContext $context, string $reversalRef, string $idempotencyKey, DateTimeImmutable $at): array
    {
        $this->caseForActor($source, $context);
        if (trim($reversalRef) === '') {
            throw new RuntimeException('Merge reversal reference is required.');
        }
        $payload = ['source' => $source->value(), 'reversal_ref' => $reversalRef];
        if ($this->eventReplay($source->value(), 'SupportCaseMergeReversed', $idempotencyKey, $payload)) {
            return $this->row($this->wpdb->prepare("SELECT * FROM {$this->tables['merge_redirects']} WHERE source_case_uuid=%s ORDER BY id DESC LIMIT 1", $source->value())) ?? [];
        }
        $this->transaction(function () use ($source, $context, $reversalRef, $idempotencyKey, $payload, $at): void {
            $updated = $this->wpdb->update($this->tables['merge_redirects'], [
                'active' => 0, 'reversal_ref' => $reversalRef, 'reversed_at' => $this->mysqlTime($at),
            ], ['source_case_uuid' => $source->value(), 'active' => 1]);
            if ($updated !== 1) {
                throw new RuntimeException('Active merge redirect was not found.');
            }
            $version = (int) $this->value($this->wpdb->prepare("SELECT record_version FROM {$this->tables['cases']} WHERE case_uuid=%s", $source->value()));
            $this->appendEvent('case', $source->value(), 'SupportCaseMergeReversed', $context, 'case_merge_reversal', $idempotencyKey, $payload, $version, $at);
        });
        return $this->row($this->wpdb->prepare("SELECT * FROM {$this->tables['merge_redirects']} WHERE source_case_uuid=%s ORDER BY id DESC LIMIT 1", $source->value())) ?? [];
    }

    /** @return array<string,mixed> */
    public function recordQuality(
        SupportCaseId $caseId,
        PrincipalContext $context,
        string $sampleBasis,
        array $scores,
        array $findings,
        bool $identitySuppressed,
        DateTimeImmutable $at
    ): array {
        $this->caseForActor($caseId, $context);
        $required = ['accuracy','accessibility','compliance','empathy','security'];
        $keys = array_keys($scores);
        sort($keys);
        if ($keys !== $required || !in_array($sampleBasis, ['random','risk','breach','reopen','complaint'], true)) {
            throw new RuntimeException('Quality review rubric is invalid.');
        }
        foreach ($scores as $score) {
            if (!is_int($score) || $score < 0 || $score > 100) {
                throw new RuntimeException('Quality scores are invalid.');
            }
        }
        $id = 'CF02-QA-' . strtoupper(bin2hex(random_bytes(10)));
        $ok = $this->wpdb->insert($this->tables['quality'], [
            'review_uuid' => $id, 'case_uuid' => $caseId->value(), 'reviewer_ref' => $context->actorReference(),
            'sample_basis' => $sampleBasis, 'scores_json' => $this->json($scores),
            'findings_hash' => hash('sha256', $this->json($findings)), 'identity_suppressed' => $identitySuppressed ? 1 : 0,
            'appealed' => 0, 'correction_ref' => null, 'reviewed_at' => $this->mysqlTime($at),
        ]);
        if ($ok !== 1) {
            throw new RuntimeException('Quality review persistence failed.');
        }
        $this->appendEvent('case', $caseId->value(), 'SupportQualityReviewRecorded', $context, 'quality_review', 'quality-' . substr(hash('sha256', $id), 0, 32), [
            'review_ref' => $id, 'sample_basis' => $sampleBasis, 'identity_suppressed' => $identitySuppressed,
        ], 1, $at);
        return $this->row($this->wpdb->prepare("SELECT * FROM {$this->tables['quality']} WHERE review_uuid=%s", $id)) ?? [];
    }

    /** @return list<array<string,mixed>> */
    public function qualitySample(int $limit): array
    {
        $limit = max(1, min(100, $limit));
        return $this->rows($this->wpdb->prepare(
            "SELECT review_uuid,case_uuid,sample_basis,scores_json,identity_suppressed,appealed,correction_ref,reviewed_at
             FROM {$this->tables['quality']} ORDER BY reviewed_at DESC LIMIT %d",
            $limit
        ));
    }

    /** @return list<array<string,mixed>> */
    public function slaAtRisk(PrincipalContext $context, int $limit): array
    {
        $rows = $this->dueSla($limit);
        if ($context->hasCapability('queue.manage')) {
            return $rows;
        }
        return array_values(array_filter($rows, static function (array $row) use ($context): bool {
            return isset($row['owner_ref']) && is_string($row['owner_ref']) && hash_equals($row['owner_ref'], $context->actorReference());
        }));
    }

    /** @return array<string,mixed> */
    public function commandStatus(string $commandId, PrincipalContext $context): array
    {
        $row = $this->command($commandId);
        if ($row === null) {
            throw new RuntimeException('Native command was not found.');
        }
        $this->caseForActor(SupportCaseId::fromString((string) $row['case_uuid']), $context);
        return array_intersect_key($row, array_flip(['command_uuid','case_uuid','native_owner','action_key','object_ref','expected_native_version','state','attempts','outcome_ref','record_version','created_at','updated_at']));
    }

    /** @return array<string,mixed> */
    public function conflictCheck(string $appealId, string $reviewerRef, PrincipalContext $context): array
    {
        $appeal = $this->appealForActor($appealId, $context);
        /** @var mixed $facts */
        $facts = apply_filters('cf02_appeal_reviewer_facts', null, $reviewerRef, $appealId, (string) $appeal['original_decision_ref']);
        $available = is_array($facts) && is_string($facts['contract_version'] ?? null) && $facts['contract_version'] !== '';
        return [
            'appeal_id' => $appealId,
            'reviewer_ref' => $reviewerRef,
            'self_review' => hash_equals((string) $appeal['appellant_ref'], $reviewerRef),
            'prior_involvement' => $available ? (bool) ($facts['prior_involvement'] ?? true) : true,
            'conflicted' => $available ? (bool) ($facts['conflicted'] ?? true) : true,
            'competent' => $available && (bool) ($facts['competent'] ?? false),
            'available' => $available && (bool) ($facts['available'] ?? false),
            'facts_contract_available' => $available,
            'eligible' => $available && !hash_equals((string) $appeal['appellant_ref'], $reviewerRef)
                && ($facts['prior_involvement'] ?? true) === false && ($facts['conflicted'] ?? true) === false
                && ($facts['competent'] ?? false) === true && ($facts['available'] ?? false) === true,
        ];
    }

    /** @return array<string,mixed> */
    public function slaMetrics(): array
    {
        $rows = $this->rows("SELECT status,COUNT(*) AS total FROM {$this->tables['sla']} GROUP BY status ORDER BY status");
        return ['generated_at' => gmdate(DATE_ATOM), 'groups' => $rows, 'privacy_safe' => true];
    }

    /** @return array<string,mixed> */
    public function reopenMetrics(): array
    {
        $rows = $this->rows($this->wpdb->prepare(
            "SELECT c.category,COUNT(*) AS reopen_events FROM {$this->tables['events']} e JOIN {$this->tables['cases']} c ON c.case_uuid=e.aggregate_ref WHERE e.event_type=%s GROUP BY c.category HAVING COUNT(*)>=5 ORDER BY c.category",
            'SupportCaseReopened'
        ));
        return ['generated_at' => gmdate(DATE_ATOM), 'groups' => $rows, 'identity_threshold' => 5, 'privacy_safe' => true];
    }

    /** @return list<array<string,mixed>> */
    public function linkedDomainProjection(SupportCaseId $caseId, PrincipalContext $context): array
    {
        $this->caseForActor($caseId, $context);
        return $this->rows($this->wpdb->prepare(
            "SELECT link_uuid,owner_key,object_type,object_ref,object_version,privacy_class,projection_hash,state,updated_at FROM {$this->tables['case_links']} WHERE case_uuid=%s AND state='active' ORDER BY id ASC",
            $caseId->value()
        ));
    }

    /** @return array<string,mixed> */
    public function exportStatus(SupportCaseId $caseId, PrincipalContext $context): array
    {
        $this->caseForActor($caseId, $context);
        /** @var mixed $status */
        $status = apply_filters('cf02_export_case_package_status', null, $caseId->value(), $context->actorReference());
        if (!is_array($status)) {
            return ['case_id' => $caseId->value(), 'state' => 'not_requested', 'provider_available' => false];
        }
        return array_intersect_key($status, array_flip(['case_id','state','manifest_hash','expires_at','provider_ref','error_code']));
    }

    /** @return list<array<string,mixed>> */
    public function purgeReconciliation(string $caseId, PrincipalContext $context): array
    {
        $case = $this->row($this->wpdb->prepare("SELECT case_uuid FROM {$this->tables['cases']} WHERE case_uuid=%s LIMIT 1", $caseId));
        if ($case !== null) {
            $this->caseForActor(SupportCaseId::fromString($caseId), $context);
        } elseif (!$context->hasAnyCapability('retention.review', 'reconciliation.manage', 'audit.read')) {
            throw new RuntimeException('Retention reconciliation access is denied.');
        }
        return $this->rows($this->wpdb->prepare(
            "SELECT object_type,object_ref,policy_version,action_key,provider_results_json,evidence_hash,executed_at FROM {$this->tables['retention']} WHERE object_type='case' AND object_ref=%s ORDER BY id DESC LIMIT 100",
            $caseId
        ));
    }

    /** @return array<string,mixed> */
    public function linkMajorIncident(SupportCaseId $caseId, PrincipalContext $context, string $incidentRef, string $nativeOwner, string $publicStatus, string $idempotencyKey, DateTimeImmutable $at): array
    {
        $this->caseForActor($caseId, $context);
        if (trim($incidentRef) === '' || preg_match('/^[a-z][a-z0-9_]{2,63}$/', $nativeOwner) !== 1 || trim($publicStatus) === '') {
            throw new RuntimeException('Major-incident link metadata is invalid.');
        }
        $existing = $this->row($this->wpdb->prepare("SELECT * FROM {$this->tables['incident_links']} WHERE incident_ref=%s AND case_uuid=%s LIMIT 1", $incidentRef, $caseId->value()));
        if ($existing !== null) {
            return $existing;
        }
        $this->transaction(function () use ($caseId, $context, $incidentRef, $nativeOwner, $publicStatus, $idempotencyKey, $at): void {
            $ok = $this->wpdb->insert($this->tables['incident_links'], [
                'incident_ref' => $incidentRef, 'case_uuid' => $caseId->value(), 'link_role' => 'affected_case',
                'public_status' => $publicStatus, 'native_owner' => $nativeOwner, 'linked_at' => $this->mysqlTime($at), 'unlinked_at' => null,
            ]);
            if ($ok !== 1) {
                throw new RuntimeException('Major-incident linkage failed.');
            }
            $this->appendEvent('case', $caseId->value(), 'SupportMajorIncidentLinked', $context, 'major_incident_link', $idempotencyKey, [
                'incident_ref' => $incidentRef, 'native_owner' => $nativeOwner, 'public_status' => $publicStatus,
            ], 1, $at);
        });
        return $this->row($this->wpdb->prepare("SELECT * FROM {$this->tables['incident_links']} WHERE incident_ref=%s AND case_uuid=%s", $incidentRef, $caseId->value())) ?? [];
    }

    /** @return array<string,mixed> */
    public function reviseInternalNote(string $messageId, SupportCaseId $caseId, PrincipalContext $context, int $expectedVersion, string $ciphertext, string $bodyHash, DateTimeImmutable $at): array
    {
        $this->caseForActor($caseId, $context);
        $message = $this->row($this->wpdb->prepare("SELECT * FROM {$this->tables['messages']} WHERE message_uuid=%s AND case_uuid=%s LIMIT 1", $messageId, $caseId->value()));
        if ($message === null || !in_array((string) $message['visibility'], ['internal','restricted'], true) || (int) $message['record_version'] !== $expectedVersion) {
            throw new RuntimeException('Internal note is not editable or is stale.');
        }
        $this->transaction(function () use ($messageId, $caseId, $context, $expectedVersion, $ciphertext, $bodyHash, $at): void {
            $revision = 1 + (int) $this->value($this->wpdb->prepare("SELECT COALESCE(MAX(revision),0) FROM {$this->tables['note_revisions']} WHERE message_uuid=%s", $messageId));
            $ok = $this->wpdb->insert($this->tables['note_revisions'], [
                'message_uuid' => $messageId, 'revision' => $revision, 'editor_ref' => $context->actorReference(),
                'body_ciphertext' => $ciphertext, 'body_hash' => $bodyHash, 'edited_at' => $this->mysqlTime($at),
            ]);
            if ($ok !== 1) {
                throw new RuntimeException('Internal-note revision history failed.');
            }
            $updated = $this->wpdb->update($this->tables['messages'], [
                'body_ciphertext' => $ciphertext, 'body_hash' => $bodyHash, 'edited_at' => $this->mysqlTime($at),
                'record_version' => $expectedVersion + 1,
            ], ['message_uuid' => $messageId, 'case_uuid' => $caseId->value(), 'record_version' => $expectedVersion]);
            if ($updated !== 1) {
                throw new RuntimeException('Internal-note update conflicted.');
            }
        });
        return $this->row($this->wpdb->prepare("SELECT message_uuid,case_uuid,author_ref,visibility,body_hash,record_version,created_at,edited_at FROM {$this->tables['messages']} WHERE message_uuid=%s", $messageId)) ?? [];
    }

    /** @param array<string,mixed> $payload */
    private function eventReplay(string $aggregateRef, string $eventType, string $idempotencyKey, array $payload): bool
    {
        $existing = $this->row($this->wpdb->prepare(
            "SELECT aggregate_ref,payload_hash FROM {$this->tables['events']} WHERE idempotency_key=%s AND event_type=%s LIMIT 1",
            $idempotencyKey, $eventType
        ));
        if ($existing === null) {
            return false;
        }
        $payloadHash = hash('sha256', $this->json($payload));
        if (!hash_equals((string) $existing['aggregate_ref'], $aggregateRef)
            || !hash_equals((string) $existing['payload_hash'], $payloadHash)) {
            throw new RuntimeException('Idempotency key was reused with a different aggregate or payload.');
        }
        return true;
    }

    /** @return list<string> */
    private function representedRefs(PrincipalContext $context): array
    {
        $rows = $this->rows($this->wpdb->prepare(
            "SELECT requester_ref FROM {$this->tables['representatives']}
             WHERE representative_ref=%s AND revoked_at IS NULL AND expires_at>UTC_TIMESTAMP(6)",
            $context->actorReference()
        ));
        $refs = [];
        foreach ($rows as $row) {
            $refs[] = (string) $row['requester_ref'];
        }
        return array_values(array_unique($refs));
    }

    private function transaction(callable $callback): void
    {
        $this->wpdb->query('START TRANSACTION');
        try {
            $callback();
            $this->wpdb->query('COMMIT');
        } catch (\Throwable $error) {
            $this->wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @return array<string,mixed>|null */
    private function row(string $sql): ?array
    {
        $row = $this->wpdb->get_row($sql, ARRAY_A);
        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    private function rows(string $sql): array
    {
        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    private function value(string $sql): mixed
    {
        return $this->wpdb->get_var($sql);
    }

    private function mysqlTime(DateTimeImmutable $at): string
    {
        return $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    /** @param mixed $value */
    private function json(mixed $value): string
    {
        $json = wp_json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (!is_string($json)) {
            throw new RuntimeException('Canonical JSON encoding failed.');
        }
        return $json;
    }


    /** @return array<string,mixed>|null */
    public function nativeResultEvidence(string $commandId): ?array
    {
        $row = $this->row($this->wpdb->prepare(
            "SELECT payload_json FROM {$this->tables['events']} WHERE aggregate_type='case' AND event_type='SupportNativeCommandResultRecorded' AND JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.command_ref'))=%s ORDER BY id DESC LIMIT 1",
            $commandId
        ));
        if ($row === null) {
            return null;
        }
        $payload = json_decode((string) $row['payload_json'], true);
        return is_array($payload) ? $payload : null;
    }

    /** @param array<string,mixed> $payload */
    public function appendWorkerEvent(string $aggregateType, string $aggregateRef, string $eventType, array $payload, int $objectVersion, DateTimeImmutable $at): string
    {
        return $this->appendEvent(
            $aggregateType, $aggregateRef, $eventType, $this->systemContext($at), 'system_worker',
            'worker-' . strtolower($eventType) . '-' . substr(hash('sha256', $aggregateRef . "\0" . $this->json($payload)), 0, 40),
            $payload, $objectVersion, $at
        );
    }


    private function systemContext(DateTimeImmutable $at): PrincipalContext
    {
        return new PrincipalContext(
            'system:cf02', 1, ['system'], ['system.worker'], [], false, $at,
            'File 00', '1.0.0-system', $at, $at->modify('+5 minutes')
        );
    }
}
