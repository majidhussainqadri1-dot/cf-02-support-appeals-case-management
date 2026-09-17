from pathlib import Path

ROOT = Path('.')

def read(path: str) -> str:
    return (ROOT / path).read_text()

def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content)

worker_path = 'src/Infrastructure/WordPress/RuntimeWorker.php'
worker = read(worker_path)

start = worker.index('    public function processSla(int $limit = 100): array\n')
end = worker.index('    public function processKeyRotation(int $limit = 100): array\n', start)
worker = worker[:start] + '''    public function processSla(int $limit = 100): array
    {
        $processed = $atRisk = $breached = 0;
        foreach ($this->repository->dueSla($limit) as $timer) {
            $caseId = (string) $timer['case_uuid'];
            if (!$this->repository->acquireWorkerLease('sla', $caseId)) {
                continue;
            }
            try {
                ++$processed;
                $now = $this->now();
                $deadlines = [];
                if ((int) ($timer['first_response_recorded'] ?? 0) !== 1) {
                    $deadlines[] = new DateTimeImmutable((string) $timer['first_response_deadline'], new DateTimeZone('UTC'));
                }
                $deadlines[] = new DateTimeImmutable((string) $timer['update_deadline'], new DateTimeZone('UTC'));
                $deadlines[] = new DateTimeImmutable((string) $timer['resolution_deadline'], new DateTimeZone('UTC'));
                $earliest = min(array_map(static fn (DateTimeImmutable $date): int => $date->getTimestamp(), $deadlines));
                $status = $earliest <= $now->getTimestamp() ? 'breached' : 'at_risk';
                if (hash_equals((string) ($timer['status'] ?? ''), $status)) {
                    continue;
                }
                $version = $this->repository->markSlaStatus($caseId, $status, $now);
                $this->repository->appendWorkerEvent(
                    'case', $caseId, $status === 'breached' ? 'SupportSlaBreached' : 'SupportSlaAtRisk',
                    ['status' => $status, 'priority' => (string) $timer['priority'], 'policy_id' => (string) $timer['policy_id'], 'policy_version' => (string) $timer['policy_version']],
                    $version, $now
                );
                $status === 'breached' ? ++$breached : ++$atRisk;
                /** @var mixed $escalation */
                $escalation = apply_filters('cf02_sla_escalation_request', null, [
                    'case_id' => $caseId, 'priority' => $timer['priority'], 'status' => $status,
                    'owner_ref' => $timer['owner_ref'], 'requester_ref' => $timer['requester_ref'],
                    'requires_human_update' => true,
                ]);
                do_action('cf02_sla_state_changed', $caseId, $status, $escalation);
            } finally {
                $this->repository->releaseWorkerLease('sla', $caseId);
            }
        }
        return compact('processed', 'atRisk', 'breached');
    }

''' + worker[end:]

start = worker.index('    public function processRetention(int $limit = 250): array\n')
end = worker.index('    private function nextAttempt(int $attempt): DateTimeImmutable\n', start)
worker = worker[:start] + '''    public function processRetention(int $limit = 250): array
    {
        $processed = $purged = $deferred = 0;
        foreach ($this->repository->dueRetention($limit) as $case) {
            $caseId = (string) $case['case_uuid'];
            if (!$this->repository->acquireWorkerLease('retention', $caseId)) {
                continue;
            }
            try {
                ++$processed;
                /** @var mixed $result */
                $result = apply_filters('cf02_retention_purge_request', null, [
                    'case_id' => $caseId,
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
                        $this->repository->purgeCase($caseId, $result, $this->now());
                        $this->repository->recordRetentionResult('case', $caseId, 'cf02-retention-v1', 'purged', $result, $this->now());
                        ++$purged;
                    } catch (Throwable $error) {
                        $this->repository->recordRetentionResult('case', $caseId, 'cf02-retention-v1', 'deferred', ['reason' => $error->getMessage()], $this->now());
                        ++$deferred;
                    }
                } else {
                    $this->repository->recordRetentionResult(
                        'case', $caseId, 'cf02-retention-v1', 'deferred',
                        is_array($result) ? $result : ['reason' => 'provider_unavailable'], $this->now()
                    );
                    ++$deferred;
                }
            } finally {
                $this->repository->releaseWorkerLease('retention', $caseId);
            }
        }
        return compact('processed', 'purged', 'deferred');
    }

''' + worker[end:]
write(worker_path, worker)

test_path = 'tests/c2n-r24-r33.php'
test = read(test_path)
# Repair an older regression literal that interpolated a PHP variable instead of matching source text.
test = test.replace('assert(str_contains($repo,"\'state\' => $current"));', 'assert(str_contains($repo,"\'state\' => \\$current"));')
marker = '\nif($failures!==[]){'
if marker not in test:
    raise SystemExit('R33 regression insertion marker missing')
addition = '''
$test(33,'SLA escalation and retention purge workers are serialized before external side effects',static function()use($read):void{
    $worker=$read('src/Infrastructure/WordPress/RuntimeWorker.php');
    assert(str_contains($worker,"acquireWorkerLease('sla', \\$caseId)"));
    assert(str_contains($worker,"releaseWorkerLease('sla', \\$caseId)"));
    assert(str_contains($worker,"acquireWorkerLease('retention', \\$caseId)"));
    assert(str_contains($worker,"releaseWorkerLease('retention', \\$caseId)"));
    assert(strpos($worker,"acquireWorkerLease('sla', \\$caseId)") < strpos($worker,'cf02_sla_escalation_request'));
    assert(strpos($worker,"acquireWorkerLease('retention', \\$caseId)") < strpos($worker,'cf02_retention_purge_request'));
});
'''
if "$test(33,'SLA escalation and retention purge workers are serialized before external side effects'" not in test:
    test = test.replace(marker, addition + marker, 1)
test = test.replace('CF-02 R24-R33 regression register passed through R32.', 'CF-02 R24-R33 regression register passed through R33.', 1)
write(test_path, test)

print('R33 corrections materialized')
