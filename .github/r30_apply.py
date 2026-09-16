from pathlib import Path

ROOT = Path('.')


def read(path: str) -> str:
    return (ROOT / path).read_text()


def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content)


def replace_once(text: str, old: str, new: str, label: str) -> str:
    if old not in text:
        raise SystemExit(f"R30 expected block missing: {label}")
    return text.replace(old, new, 1)

# -----------------------------------------------------------------------------
# R30-04: bind event replay to aggregate type as well as aggregate reference.
# -----------------------------------------------------------------------------
repo_path = 'src/Infrastructure/WordPress/OperationsRepository.php'
repo = read(repo_path)
repo = replace_once(
    repo,
    "if ($this->eventReplay($appealId, $event, $idempotencyKey, $payload)) {",
    "if ($this->eventReplay($appealId, $event, $idempotencyKey, $payload, 'appeal')) {",
    'appeal replay aggregate type',
)
old = '''    /** @param array<string,mixed> $payload */
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
'''
new = '''    /** @param array<string,mixed> $payload */
    private function eventReplay(
        string $aggregateRef,
        string $eventType,
        string $idempotencyKey,
        array $payload,
        string $aggregateType = 'case'
    ): bool {
        $existing = $this->row($this->wpdb->prepare(
            "SELECT aggregate_type,aggregate_ref,payload_hash FROM {$this->tables['events']} WHERE idempotency_key=%s AND event_type=%s LIMIT 1",
            $idempotencyKey, $eventType
        ));
        if ($existing === null) {
            return false;
        }
        $payloadHash = hash('sha256', $this->json($payload));
        if (!hash_equals((string) $existing['aggregate_type'], $aggregateType)
            || !hash_equals((string) $existing['aggregate_ref'], $aggregateRef)
            || !hash_equals((string) $existing['payload_hash'], $payloadHash)) {
            throw new RuntimeException('Idempotency key was reused with a different aggregate type, aggregate, or payload.');
        }
        return true;
    }
'''
repo = replace_once(repo, old, new, 'eventReplay implementation')

# -----------------------------------------------------------------------------
# R30-01: schema-free connection-scoped worker leases around external effects.
# -----------------------------------------------------------------------------
marker = '''    /** @return list<array<string,mixed>> */
    public function pendingEvents(int $limit): array
'''
lease_methods = '''    public function acquireWorkerLease(string $scope, string $objectId): bool
    {
        if (preg_match('/^[a-z][a-z0-9_-]{1,31}$/', $scope) !== 1 || trim($objectId) === '') {
            throw new RuntimeException('Worker lease identity is invalid.');
        }
        $lockName = 'cf02:' . $scope . ':' . substr(hash('sha256', $objectId), 0, 40);
        $acquired = $this->value($this->wpdb->prepare('SELECT GET_LOCK(%s,0)', $lockName));
        return (int) $acquired === 1;
    }

    public function releaseWorkerLease(string $scope, string $objectId): void
    {
        if (preg_match('/^[a-z][a-z0-9_-]{1,31}$/', $scope) !== 1 || trim($objectId) === '') {
            return;
        }
        $lockName = 'cf02:' . $scope . ':' . substr(hash('sha256', $objectId), 0, 40);
        $this->value($this->wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lockName));
    }

'''
repo = replace_once(repo, marker, lease_methods + marker, 'worker lease insertion point')

# -----------------------------------------------------------------------------
# R30-02: exact concurrent replay recovery for native commands.
# -----------------------------------------------------------------------------
old = '''        $this->transaction(function () use ($id, $caseId, $nativeOwner, $action, $objectRef, $expectedNativeVersion, $idempotencyKey, $payloadHash, $payloadCiphertext, $context, $purpose, $at): void {
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
'''
new = '''        try {
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
        } catch (RuntimeException $error) {
            $replayed = $this->row($this->wpdb->prepare(
                "SELECT * FROM {$this->tables['commands']} WHERE idempotency_key=%s LIMIT 1",
                $idempotencyKey
            ));
            if ($replayed !== null
                && hash_equals((string) $replayed['case_uuid'], $caseId->value())
                && hash_equals((string) $replayed['native_owner'], $nativeOwner)
                && hash_equals((string) $replayed['action_key'], $action)
                && hash_equals((string) $replayed['object_ref'], $objectRef)
                && (int) $replayed['expected_native_version'] === $expectedNativeVersion
                && hash_equals((string) $replayed['payload_hash'], $payloadHash)) {
                return $replayed;
            }
            throw $error;
        }
        return $this->row($this->wpdb->prepare("SELECT * FROM {$this->tables['commands']} WHERE command_uuid=%s", $id)) ?? [];
'''
repo = replace_once(repo, old, new, 'native command concurrent replay')

# -----------------------------------------------------------------------------
# R30-02: exact concurrent replay recovery for outbox deliveries.
# -----------------------------------------------------------------------------
old = '''        $this->transaction(function () use ($messageId, $caseId, $recipientRef, $channel, $templateKey, $payloadHash, $payloadCiphertext, $idempotencyKey, $at): void {
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
'''
new = '''        try {
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
        } catch (RuntimeException $error) {
            $replayed = $this->row($this->wpdb->prepare(
                "SELECT * FROM {$this->tables['outbox']} WHERE idempotency_key=%s LIMIT 1",
                $idempotencyKey
            ));
            if ($replayed !== null
                && hash_equals((string) $replayed['case_uuid'], $caseId->value())
                && hash_equals((string) $replayed['template_key'], $templateKey)
                && hash_equals((string) $replayed['payload_hash'], $payloadHash)
                && hash_equals((string) $replayed['recipient_ref'], $recipientRef)
                && hash_equals((string) $replayed['channel'], $channel)) {
                return (string) $replayed['message_uuid'];
            }
            throw $error;
        }
        return $messageId;
'''
repo = replace_once(repo, old, new, 'outbox concurrent replay')

# -----------------------------------------------------------------------------
# R30-03: serialize merge redirects and require canonical targets.
# -----------------------------------------------------------------------------
start = repo.index("    /** @return array<string,mixed> */\n    public function mergeCases(")
end = repo.index("    /** @return array<string,mixed> */\n    public function splitMergedCase(", start)
merge_method = '''    /** @return array<string,mixed> */
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
        $result = null;
        $this->transaction(function () use ($source, $target, $context, $reason, $idempotencyKey, $payload, $at, &$result): void {
            $lockIds = [$source->value(), $target->value()];
            sort($lockIds, SORT_STRING);
            foreach ($lockIds as $caseId) {
                $this->lockCaseForLifecycle($caseId);
            }
            $existing = $this->row($this->wpdb->prepare(
                "SELECT * FROM {$this->tables['merge_redirects']} WHERE source_case_uuid=%s AND active=1 LIMIT 1 FOR UPDATE",
                $source->value()
            ));
            if ($existing !== null) {
                if (!hash_equals((string) $existing['target_case_uuid'], $target->value())) {
                    throw new RuntimeException('Source case is already redirected to another target.');
                }
                $result = $existing;
                return;
            }
            $targetRedirect = $this->row($this->wpdb->prepare(
                "SELECT source_case_uuid,target_case_uuid FROM {$this->tables['merge_redirects']} WHERE source_case_uuid=%s AND active=1 LIMIT 1 FOR UPDATE",
                $target->value()
            ));
            if ($targetRedirect !== null) {
                throw new RuntimeException('Merge target must be canonical and not already redirected.');
            }
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
            $result = $this->row($this->wpdb->prepare(
                "SELECT * FROM {$this->tables['merge_redirects']} WHERE source_case_uuid=%s AND active=1 LIMIT 1",
                $source->value()
            ));
        });
        return is_array($result) ? $result : [];
    }

'''
repo = repo[:start] + merge_method + repo[end:]
write(repo_path, repo)

# -----------------------------------------------------------------------------
# R30-01: wrap event/outbox/command external effects with named leases.
# -----------------------------------------------------------------------------
worker_path = 'src/Infrastructure/WordPress/RuntimeWorker.php'
worker = read(worker_path)

start = worker.index("    public function processEvents(int $limit = 100): array\n")
end = worker.index("    public function processOutbox(int $limit = 100): array\n", start)
process_events = '''    public function processEvents(int $limit = 100): array
    {
        $processed = $published = $retried = 0;
        foreach ($this->repository->pendingEvents($limit) as $event) {
            $eventId = (string) $event['event_uuid'];
            if (!$this->repository->acquireWorkerLease('event', $eventId)) {
                continue;
            }
            try {
                ++$processed;
                try {
                    /** @var mixed $result */
                    $result = apply_filters('cf02_publish_domain_event', null, $event);
                    if (!is_array($result) || ($result['accepted'] ?? false) !== true) {
                        throw new RuntimeException('Event consumer did not accept the event.');
                    }
                    $this->repository->markEventPublished($eventId);
                    do_action('cf02_domain_event_published', $event);
                    ++$published;
                } catch (Throwable) {
                    $attempts = (int) $event['publish_attempts'] + 1;
                    $this->repository->markEventRetry($eventId, $attempts, $this->nextAttempt($attempts));
                    ++$retried;
                }
            } finally {
                $this->repository->releaseWorkerLease('event', $eventId);
            }
        }
        return compact('processed', 'published', 'retried');
    }

'''
worker = worker[:start] + process_events + worker[end:]

start = worker.index("    public function processOutbox(int $limit = 100): array\n")
end = worker.index("    public function processCommands(int $limit = 100): array\n", start)
process_outbox = '''    public function processOutbox(int $limit = 100): array
    {
        $processed = $sent = $retried = $deadLetters = 0;
        foreach ($this->repository->pendingOutbox($limit) as $message) {
            $messageId = (string) $message['message_uuid'];
            if (!$this->repository->acquireWorkerLease('outbox', $messageId)) {
                continue;
            }
            try {
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
                        $messageId, 'sent', $attempts,
                        isset($result['provider_ref']) ? (string) $result['provider_ref'] : null,
                        null, $this->now()
                    );
                    ++$sent;
                } catch (Throwable) {
                    $dead = $attempts >= 8;
                    $this->repository->updateOutboxResult(
                        $messageId, $dead ? 'dead_letter' : 'retry', $attempts,
                        null, $dead ? null : $this->nextAttempt($attempts), $this->now()
                    );
                    if ($dead && (string) ($message['template_key'] ?? '') === 'support_case_resolved') {
                        $this->repository->recoverOutcomeDeliveryFailure(
                            (string) $message['case_uuid'], $messageId, $this->now()
                        );
                    }
                    $dead ? ++$deadLetters : ++$retried;
                }
            } finally {
                $this->repository->releaseWorkerLease('outbox', $messageId);
            }
        }
        return compact('processed', 'sent', 'retried', 'deadLetters');
    }

'''
worker = worker[:start] + process_outbox + worker[end:]

start = worker.index("    public function processCommands(int $limit = 100): array\n")
end = worker.index("    public function processSla(int $limit = 100): array\n", start)
old_commands = worker[start:end]
# Preserve the existing command processing semantics exactly; only add the lease shell.
lines = old_commands.splitlines()
# This transformation is deliberately explicit rather than regex-heavy so validation catches drift.
if "        foreach ($this->repository->pendingCommands($limit) as $command) {" not in old_commands:
    raise SystemExit('R30 command worker foreach missing')
old_commands = old_commands.replace(
    "        foreach ($this->repository->pendingCommands($limit) as $command) {\n            ++$processed;",
    "        foreach ($this->repository->pendingCommands($limit) as $command) {\n            $commandId = (string) $command['command_uuid'];\n            if (!$this->repository->acquireWorkerLease('command', $commandId)) {\n                continue;\n            }\n            try {\n                ++$processed;",
    1,
)
needle = "        }\n        return compact('processed', 'succeeded', 'failed', 'retried', 'uncertain', 'deadLetters');\n    }\n\n"
replacement = "            } finally {\n                $this->repository->releaseWorkerLease('command', $commandId);\n            }\n        }\n        return compact('processed', 'succeeded', 'failed', 'retried', 'uncertain', 'deadLetters');\n    }\n\n"
if needle not in old_commands:
    raise SystemExit('R30 command worker tail missing')
old_commands = old_commands.replace(needle, replacement, 1)
worker = worker[:start] + old_commands + worker[end:]
write(worker_path, worker)

# -----------------------------------------------------------------------------
# Regression register R30.
# -----------------------------------------------------------------------------
test_path = 'tests/c2n-r24-r33.php'
test = read(test_path)
old_tail = '''$test(29,'SLA worker emits escalation side effects only on a real status transition',static function()use($read):void{
    $worker=$read('src/Infrastructure/WordPress/RuntimeWorker.php');
    assert(str_contains($worker, "if (hash_equals((string) (\\$timer['status'] ?? ''), \\$status))"));
});

if($failures!==[]){fwrite(STDERR,implode("\\n",$failures)."\\n");exit(1);}fwrite(STDOUT,"CF-02 R24-R33 regression register passed through R29.\\n");
'''
new_tail = '''$test(29,'SLA worker emits escalation side effects only on a real status transition',static function()use($read):void{
    $worker=$read('src/Infrastructure/WordPress/RuntimeWorker.php');
    assert(str_contains($worker, "if (hash_equals((string) (\\$timer['status'] ?? ''), \\$status))"));
});
$test(30,'worker side effects are concurrency leased and replay identity is aggregate-type bound',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    $worker=$read('src/Infrastructure/WordPress/RuntimeWorker.php');
    assert(str_contains($repo, "SELECT GET_LOCK(%s,0)"));
    assert(str_contains($repo, "SELECT RELEASE_LOCK(%s)"));
    assert(str_contains($worker, "acquireWorkerLease('event'"));
    assert(str_contains($worker, "acquireWorkerLease('outbox'"));
    assert(str_contains($worker, "acquireWorkerLease('command'"));
    assert(substr_count($worker, 'releaseWorkerLease(')>=3);
    assert(str_contains($repo, "SELECT aggregate_type,aggregate_ref,payload_hash"));
    assert(str_contains($repo, "eventReplay(\\$appealId, \\$event, \\$idempotencyKey, \\$payload, 'appeal')"));
    assert(str_contains($repo, "Merge target must be canonical and not already redirected."));
    assert(str_contains($repo, "sort(\\$lockIds, SORT_STRING)"));
    assert(substr_count($repo, "WHERE idempotency_key=%s LIMIT 1")>=4);
});

if($failures!==[]){fwrite(STDERR,implode("\\n",$failures)."\\n");exit(1);}fwrite(STDOUT,"CF-02 R24-R33 regression register passed through R30.\\n");
'''
test = replace_once(test, old_tail, new_tail, 'R30 regression register tail')
write(test_path, test)

print('R30 corrections materialized.')
