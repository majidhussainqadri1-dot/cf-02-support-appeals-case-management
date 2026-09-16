from pathlib import Path


def rep(path: str, old: str, new: str, label: str) -> None:
    p=Path(path); s=p.read_text()
    if old not in s: raise SystemExit(f'R31 missing block: {label}')
    p.write_text(s.replace(old,new,1))

# Import canonical case identifier.
rep('src/Infrastructure/WordPress/RuntimeWorker.php',
    "use RuntimeException;\nuse Sabri\\CF02\\Security\\DataCipher;",
    "use RuntimeException;\nuse Sabri\\CF02\\Domain\\SupportCaseId;\nuse Sabri\\CF02\\Security\\DataCipher;",
    'SupportCaseId import')

p=Path('src/Infrastructure/WordPress/RuntimeWorker.php'); s=p.read_text()
start=s.index('    public function processEvents(int $limit = 100): array\n')
end=s.index('    public function processOutbox(int $limit = 100): array\n',start)
new_events='''    public function processEvents(int $limit = 100): array
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
                    ++$published;
                } catch (Throwable) {
                    $attempts = (int) $event['publish_attempts'] + 1;
                    $this->repository->markEventRetry($eventId, $attempts, $this->nextAttempt($attempts));
                    ++$retried;
                    continue;
                }
                // Publication is already durable. Observer failures must never reopen/retry it.
                try {
                    do_action('cf02_domain_event_published', $event);
                } catch (Throwable) {
                    // Non-authoritative observer failure is isolated from publication state.
                }
            } finally {
                $this->repository->releaseWorkerLease('event', $eventId);
            }
        }
        return compact('processed', 'published', 'retried');
    }

'''
s=s[:start]+new_events+s[end:]
start=s.index('    public function processCommands(int $limit = 100): array\n')
end=s.index('    public function processSla(int $limit = 100): array\n',start)
new_commands='''    public function processCommands(int $limit = 100): array
    {
        $processed = $succeeded = $failed = $retried = $uncertain = $deadLetters = 0;
        foreach ($this->repository->pendingCommands($limit) as $command) {
            $commandId = (string) $command['command_uuid'];
            if (!$this->repository->acquireWorkerLease('command', $commandId)) {
                continue;
            }
            try {
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
                        $this->repository->updateCommandResult($commandId, 'succeeded', $outcomeRef, $attempts, null, $this->now());
                        ++$succeeded;
                    } elseif ($state === 'failed') {
                        $this->repository->updateCommandResult($commandId, 'failed', $outcomeRef === '' ? null : $outcomeRef, $attempts, null, $this->now());
                        ++$failed;
                    } elseif ($state === 'outcome_uncertain') {
                        $this->repository->updateCommandResult($commandId, 'outcome_uncertain', null, $attempts, $this->nextAttempt($attempts), $this->now());
                        ++$uncertain;
                    } else {
                        throw new RuntimeException('Native owner did not return a supported result state.');
                    }
                } catch (Throwable $dispatchError) {
                    $current = $this->repository->command($commandId);
                    if (is_array($current) && in_array((string) ($current['state'] ?? ''), ['succeeded','failed','dead_letter'], true)) {
                        continue;
                    }
                    $dead = $attempts >= 8;
                    $this->repository->updateCommandResult(
                        $commandId, $dead ? 'dead_letter' : 'retry', null,
                        $attempts, $dead ? null : $this->nextAttempt($attempts), $this->now()
                    );
                    $dead ? ++$deadLetters : ++$retried;
                    continue;
                }

                // Authoritative command state is now persisted. Projection/event/SLA failures
                // must never rewrite a succeeded/failed command back to retry/dead-letter.
                try {
                    $this->repository->appendWorkerEvent(
                        'case', (string) $command['case_uuid'], 'SupportNativeCommandResultRecorded', [
                            'command_ref' => $commandId, 'status' => $state,
                            'outcome_ref' => $outcomeRef, 'native_version' => $nativeVersion,
                        ], (int) $command['record_version'] + 1, $this->now()
                    );
                    if (in_array($state, ['succeeded','failed'], true)) {
                        $caseId = SupportCaseId::fromString((string) $command['case_uuid']);
                        if ($this->repository->resumeSla($caseId, 'native-command:' . $commandId, $this->now(), 'waiting_provider')) {
                            $this->repository->appendWorkerEvent('case', (string) $command['case_uuid'], 'SupportSlaResumed', [
                                'reason' => 'native_owner_result', 'evidence_ref' => 'native-command:' . $commandId,
                            ], (int) $command['record_version'] + 1, $this->now());
                        }
                    }
                } catch (Throwable) {
                    // Post-result reconciliation is non-authoritative for command terminal state.
                }
            } finally {
                $this->repository->releaseWorkerLease('command', $commandId);
            }
        }
        return compact('processed', 'succeeded', 'failed', 'retried', 'uncertain', 'deadLetters');
    }

'''
s=s[:start]+new_commands+s[end:]
p.write_text(s)

# Harden event state updates and command-result monotonicity.
p=Path('src/Infrastructure/WordPress/OperationsRepository.php'); s=p.read_text()
old='''    public function markEventPublished(string $eventId): void
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
'''
new='''    public function markEventPublished(string $eventId): void
    {
        $updated = $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->tables['events']} SET publish_state='published' WHERE event_uuid=%s AND publish_state IN ('pending','retry')",
            $eventId
        ));
        if ($updated === 1) {
            return;
        }
        $state = $this->value($this->wpdb->prepare("SELECT publish_state FROM {$this->tables['events']} WHERE event_uuid=%s", $eventId));
        if (!is_string($state) || !hash_equals($state, 'published')) {
            throw new RuntimeException('Event publication state update conflicted.');
        }
    }

    public function markEventRetry(string $eventId, int $attempts, DateTimeImmutable $next): void
    {
        $target = $attempts >= 8 ? 'dead_letter' : 'retry';
        $updated = $this->wpdb->update($this->tables['events'], [
            'publish_state' => $target,
            'publish_attempts' => $attempts,
            'next_attempt_at' => $this->mysqlTime($next),
        ], ['event_uuid' => $eventId, 'publish_state' => 'pending']);
        if ($updated === 0) {
            $updated = $this->wpdb->update($this->tables['events'], [
                'publish_state' => $target,
                'publish_attempts' => $attempts,
                'next_attempt_at' => $this->mysqlTime($next),
            ], ['event_uuid' => $eventId, 'publish_state' => 'retry']);
        }
        if ($updated === false) {
            throw new RuntimeException('Event retry state update failed.');
        }
    }
'''
if old not in s: raise SystemExit('R31 event state block missing')
s=s.replace(old,new,1)
old='''    public function updateCommandResult(string $commandId, string $state, ?string $outcomeRef, int $attempts, ?DateTimeImmutable $next, DateTimeImmutable $at): void
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
'''
new='''    public function updateCommandResult(string $commandId, string $state, ?string $outcomeRef, int $attempts, ?DateTimeImmutable $next, DateTimeImmutable $at): void
    {
        if (!in_array($state, ['succeeded','retry','failed','outcome_uncertain','dead_letter'], true)) {
            throw new RuntimeException('Invalid command result state.');
        }
        $row = $this->row($this->wpdb->prepare(
            "SELECT state,outcome_ref,record_version FROM {$this->tables['commands']} WHERE command_uuid=%s LIMIT 1",
            $commandId
        ));
        if ($row === null) {
            throw new RuntimeException('Native command was not found.');
        }
        $current = (string) $row['state'];
        if (in_array($current, ['succeeded','failed','dead_letter'], true)) {
            if (hash_equals($current, $state)
                && hash_equals((string) ($row['outcome_ref'] ?? ''), (string) ($outcomeRef ?? ''))) {
                return;
            }
            throw new RuntimeException('Terminal native command result is immutable.');
        }
        if (!in_array($current, ['pending','retry','outcome_uncertain'], true)) {
            throw new RuntimeException('Native command current state is invalid.');
        }
        $updated = $this->wpdb->update($this->tables['commands'], [
            'state' => $state, 'outcome_ref' => $outcomeRef, 'attempts' => $attempts,
            'next_attempt_at' => $next ? $this->mysqlTime($next) : null,
            'record_version' => (int) $row['record_version'] + 1,
            'updated_at' => $this->mysqlTime($at),
        ], ['command_uuid' => $commandId, 'state' => $current, 'record_version' => (int) $row['record_version']]);
        if ($updated !== 1) {
            throw new RuntimeException('Native command result update conflicted.');
        }
    }
'''
if old not in s: raise SystemExit('R31 command result block missing')
s=s.replace(old,new,1)
p.write_text(s)

# Extend review register through R31.
p=Path('tests/c2n-r24-r33.php'); t=p.read_text()
needle='''$test(30,'worker side effects are concurrency leased and replay identity is aggregate-type bound',static function()use($read):void{
'''
if needle not in t: raise SystemExit('R31 R30 test marker missing')
insert='''$test(31,'runtime class resolution and terminal external-result states are monotonic',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    $worker=$read('src/Infrastructure/WordPress/RuntimeWorker.php');
    assert(str_contains($worker,'use Sabri\\\\CF02\\\\Domain\\\\SupportCaseId;'));
    assert(str_contains($repo,'Terminal native command result is immutable.'));
    assert(str_contains($repo,"'state' => $current"));
    assert(str_contains($repo,"publish_state IN ('pending','retry')"));
    assert(str_contains($worker,'Publication is already durable. Observer failures must never reopen/retry it.'));
    assert(str_contains($worker,'Post-result reconciliation is non-authoritative for command terminal state.'));
});

'''
# Put R31 test before final if, after existing R30 block by replacing final line.
final='''if($failures!==[]){fwrite(STDERR,implode("\\n",$failures)."\\n");exit(1);}fwrite(STDOUT,"CF-02 R24-R33 regression register passed through R30.\\n");
'''
if final not in t: raise SystemExit('R31 final marker missing')
t=t.replace(final,insert+'''if($failures!==[]){fwrite(STDERR,implode("\\n",$failures)."\\n");exit(1);}fwrite(STDOUT,"CF-02 R24-R33 regression register passed through R31.\\n");
''',1)
p.write_text(t)
print('R31 corrections materialized')
