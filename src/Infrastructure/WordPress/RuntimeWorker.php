<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Sabri\CF02\Security\DataCipher;
use Throwable;

/** Concrete bounded workers for events, File-19 delivery, native commands and retention. */
final class RuntimeWorker
{
    public function __construct(
        private readonly OperationsRepository $repository,
        private readonly DataCipher $cipher
    ) {
    }

    public function processEvents(int $limit = 100): array
    {
        $processed = $published = $retried = 0;
        foreach ($this->repository->pendingEvents($limit) as $event) {
            ++$processed;
            try {
                /** @var mixed $result */
                $result = apply_filters('cf02_publish_domain_event', null, $event);
                if (!is_array($result) || ($result['accepted'] ?? false) !== true) {
                    throw new RuntimeException('Event consumer did not accept the event.');
                }
                $this->repository->markEventPublished((string) $event['event_uuid']);
                do_action('cf02_domain_event_published', $event);
                ++$published;
            } catch (Throwable) {
                $attempts = (int) $event['publish_attempts'] + 1;
                $this->repository->markEventRetry((string) $event['event_uuid'], $attempts, $this->nextAttempt($attempts));
                ++$retried;
            }
        }
        return compact('processed', 'published', 'retried');
    }

    public function processOutbox(int $limit = 100): array
    {
        $processed = $sent = $retried = $deadLetters = 0;
        foreach ($this->repository->pendingOutbox($limit) as $message) {
            ++$processed;
            $attempts = (int) $message['attempts'] + 1;
            try {
                $payload = json_decode($this->cipher->decrypt((string) $message['payload_ciphertext']), true, 512, JSON_THROW_ON_ERROR);
                /** @var mixed $result */
                $result = apply_filters('cf02_file19_delivery_request', null, [
                    'message_id' => $message['message_uuid'],
                    'case_id' => $message['case_uuid'],
                    'channel' => $message['channel'],
                    'recipient_ref' => $message['recipient_ref'],
                    'template_key' => $message['template_key'],
                    'payload' => $payload,
                    'idempotency_key' => $message['idempotency_key'],
                ]);
                if (!is_array($result) || ($result['accepted'] ?? false) !== true) {
                    throw new RuntimeException('File 19 delivery adapter is unavailable.');
                }
                $this->repository->updateOutboxResult(
                    (string) $message['message_uuid'], 'sent', $attempts,
                    isset($result['provider_ref']) ? (string) $result['provider_ref'] : null,
                    null, $this->now()
                );
                ++$sent;
            } catch (Throwable) {
                $dead = $attempts >= 8;
                $this->repository->updateOutboxResult(
                    (string) $message['message_uuid'], $dead ? 'dead_letter' : 'retry', $attempts,
                    null, $dead ? null : $this->nextAttempt($attempts), $this->now()
                );
                $dead ? ++$deadLetters : ++$retried;
            }
        }
        return compact('processed', 'sent', 'retried', 'deadLetters');
    }

    public function processCommands(int $limit = 100): array
    {
        $processed = $succeeded = $failed = $retried = $uncertain = $deadLetters = 0;
        foreach ($this->repository->pendingCommands($limit) as $command) {
            ++$processed;
            $attempts = (int) $command['attempts'] + 1;
            try {
                $payload = json_decode($this->cipher->decrypt((string) $command['payload_ciphertext']), true, 512, JSON_THROW_ON_ERROR);
                /** @var mixed $result */
                $result = apply_filters('cf02_dispatch_native_owner_command', null, [
                    'command_id' => $command['command_uuid'],
                    'case_id' => $command['case_uuid'],
                    'native_owner' => $command['native_owner'],
                    'action' => $command['action_key'],
                    'object_ref' => $command['object_ref'],
                    'expected_native_version' => (int) $command['expected_native_version'],
                    'payload' => $payload,
                    'idempotency_key' => $command['idempotency_key'],
                ]);
                if (!is_array($result) || !isset($result['state'])) {
                    throw new RuntimeException('Native owner adapter returned no authoritative state.');
                }
                $state = (string) $result['state'];
                $nativeVersion = (int) ($result['native_version'] ?? 0);
                $outcomeRef = isset($result['outcome_ref']) ? trim((string) $result['outcome_ref']) : '';
                if ($nativeVersion < (int) $command['expected_native_version']) {
                    throw new RuntimeException('Native owner result is stale.');
                }
                if ($state === 'succeeded' && $outcomeRef !== '') {
                    $this->repository->updateCommandResult((string) $command['command_uuid'], 'succeeded', $outcomeRef, $attempts, null, $this->now());
                    ++$succeeded;
                } elseif ($state === 'failed') {
                    $this->repository->updateCommandResult((string) $command['command_uuid'], 'failed', $outcomeRef === '' ? null : $outcomeRef, $attempts, null, $this->now());
                    ++$failed;
                } elseif ($state === 'outcome_uncertain') {
                    $this->repository->updateCommandResult((string) $command['command_uuid'], 'outcome_uncertain', null, $attempts, $this->nextAttempt($attempts), $this->now());
                    ++$uncertain;
                } else {
                    throw new RuntimeException('Native owner did not return a supported result state.');
                }
                $this->repository->appendWorkerEvent(
                    'case', (string) $command['case_uuid'], 'SupportNativeCommandResultRecorded', [
                        'command_ref' => (string) $command['command_uuid'], 'status' => $state,
                        'outcome_ref' => $outcomeRef, 'native_version' => $nativeVersion,
                    ], (int) $command['record_version'] + 1, $this->now()
                );
            } catch (Throwable) {
                $dead = $attempts >= 8;
                $this->repository->updateCommandResult(
                    (string) $command['command_uuid'], $dead ? 'dead_letter' : 'retry', null,
                    $attempts, $dead ? null : $this->nextAttempt($attempts), $this->now()
                );
                $dead ? ++$deadLetters : ++$retried;
            }
        }
        return compact('processed', 'succeeded', 'failed', 'retried', 'uncertain', 'deadLetters');
    }

    public function processSla(int $limit = 100): array
    {
        $processed = $atRisk = $breached = 0;
        foreach ($this->repository->dueSla($limit) as $timer) {
            ++$processed;
            $now = $this->now();
            $deadlines = [
                new DateTimeImmutable((string) $timer['first_response_deadline'], new DateTimeZone('UTC')),
                new DateTimeImmutable((string) $timer['update_deadline'], new DateTimeZone('UTC')),
                new DateTimeImmutable((string) $timer['resolution_deadline'], new DateTimeZone('UTC')),
            ];
            $earliest = min(array_map(static fn (DateTimeImmutable $date): int => $date->getTimestamp(), $deadlines));
            $status = $earliest <= $now->getTimestamp() ? 'breached' : 'at_risk';
            $version = $this->repository->markSlaStatus((string) $timer['case_uuid'], $status, $now);
            $this->repository->appendWorkerEvent(
                'case', (string) $timer['case_uuid'], $status === 'breached' ? 'SupportSlaBreached' : 'SupportSlaAtRisk',
                ['status' => $status, 'priority' => (string) $timer['priority'], 'policy_id' => (string) $timer['policy_id'], 'policy_version' => (string) $timer['policy_version']],
                $version, $now
            );
            $status === 'breached' ? ++$breached : ++$atRisk;
            /** @var mixed $escalation */
            $escalation = apply_filters('cf02_sla_escalation_request', null, [
                'case_id' => $timer['case_uuid'], 'priority' => $timer['priority'], 'status' => $status,
                'owner_ref' => $timer['owner_ref'], 'requester_ref' => $timer['requester_ref'],
                'requires_human_update' => true,
            ]);
            do_action('cf02_sla_state_changed', $timer['case_uuid'], $status, $escalation);
        }
        return compact('processed', 'atRisk', 'breached');
    }

    public function processKeyRotation(int $limit = 100): array
    {
        return (new EncryptionRotationService($this->cipher))->rotate($limit);
    }

    public function processRetention(int $limit = 250): array
    {
        $processed = $purged = $deferred = 0;
        foreach ($this->repository->dueRetention($limit) as $case) {
            ++$processed;
            /** @var mixed $result */
            $result = apply_filters('cf02_retention_purge_request', null, [
                'case_id' => $case['case_uuid'],
                'category' => $case['category'],
                'state' => $case['state'],
                'closed_at' => $case['closed_at'],
                'required_targets' => ['canonical','attachments','cache','search','analytics','providers'],
            ]);
            $accepted = is_array($result)
                && ($result['authorized'] ?? false) === true
                && ($result['all_targets_reconciled'] ?? false) === true;
            if ($accepted) {
                try {
                    $this->repository->purgeCase((string) $case['case_uuid'], $result, $this->now());
                    $this->repository->recordRetentionResult('case', (string) $case['case_uuid'], 'cf02-retention-v1', 'purged', $result, $this->now());
                    ++$purged;
                } catch (Throwable $error) {
                    $this->repository->recordRetentionResult('case', (string) $case['case_uuid'], 'cf02-retention-v1', 'deferred', ['reason' => $error->getMessage()], $this->now());
                    ++$deferred;
                }
            } else {
                $this->repository->recordRetentionResult(
                    'case', (string) $case['case_uuid'], 'cf02-retention-v1', 'deferred',
                    is_array($result) ? $result : ['reason' => 'provider_unavailable'], $this->now()
                );
                ++$deferred;
            }
        }
        return compact('processed', 'purged', 'deferred');
    }

    private function nextAttempt(int $attempt): DateTimeImmutable
    {
        $seconds = min(86400, 30 * (2 ** min(10, max(0, $attempt - 1))));
        return $this->now()->modify('+' . $seconds . ' seconds');
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
