from pathlib import Path

ROOT = Path('.')

def read(path: str) -> str:
    return (ROOT / path).read_text()

def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content)

repo_path = 'src/Infrastructure/WordPress/OperationsRepository.php'
repo = read(repo_path)
if "acquireWorkerLease('event_chain', $chainId)" not in repo:
    start = repo.index("    /** @param array<string,mixed> $payload */\n    public function appendEvent(")
    end = repo.index("    /** @return array<string,mixed> */\n    public function caseProjection", start)
    replacement = r'''    /** @param array<string,mixed> $payload */
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
        $eventId = 'CF02-EVT-' . strtoupper(substr(hash('sha256', $aggregateType . "\0" . $aggregateRef . "\0" . $eventType . "\0" . $idempotencyKey), 0, 20));
        $chainId = $aggregateType . "\0" . $aggregateRef;
        if (!$this->acquireWorkerLease('event_chain', $chainId)) {
            throw new RuntimeException('Event evidence chain is busy; retry the mutation.');
        }
        try {
            $existing = $this->row($this->wpdb->prepare(
                "SELECT aggregate_type,aggregate_ref,payload_hash,event_hash FROM {$this->tables['events']} WHERE event_uuid=%s",
                $eventId
            ));
            if ($existing !== null) {
                if (!hash_equals((string) $existing['aggregate_type'], $aggregateType)
                    || !hash_equals((string) $existing['aggregate_ref'], $aggregateRef)
                    || !hash_equals((string) $existing['payload_hash'], $payloadHash)) {
                    throw new RuntimeException('Event idempotency collision.');
                }
                return $eventId;
            }
            $previous = $this->value($this->wpdb->prepare(
                "SELECT event_hash FROM {$this->tables['events']} WHERE aggregate_type=%s AND aggregate_ref=%s ORDER BY id DESC LIMIT 1",
                $aggregateType, $aggregateRef
            ));
            $previousHash = is_string($previous) && $previous !== '' ? $previous : null;
            $role = $context->roles()[0] ?? 'system';
            $eventHash = hash('sha256', $this->json([
                $eventId, $aggregateType, $aggregateRef, $eventType, $context->actorReference(), $role,
                $purpose, $payloadHash, $objectVersion, $previousHash, $at->format(DATE_ATOM),
            ]));
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
        } finally {
            $this->releaseWorkerLease('event_chain', $chainId);
        }
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
        $chainId = $objectType . "\0" . $objectRef;
        if (!$this->acquireWorkerLease('audit_chain', $chainId)) {
            throw new RuntimeException('Audit evidence chain is busy; retry the mutation.');
        }
        try {
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
        } finally {
            $this->releaseWorkerLease('audit_chain', $chainId);
        }
    }

'''
    repo = repo[:start] + replacement + repo[end:]
    write(repo_path, repo)

test_path = 'tests/c2o-r34-r43.php'
test = r'''<?php

declare(strict_types=1);
ini_set('assert.exception','1');
assert_options(ASSERT_ACTIVE,1);
assert_options(ASSERT_EXCEPTION,1);
$root=dirname(__DIR__);
$failures=[];
$test=static function(int $round,string $name,callable $callback)use(&$failures):void{
    try{$callback();fwrite(STDOUT,sprintf("PASS REVIEW %02d %s\n",$round,$name));}
    catch(Throwable $e){$failures[]=sprintf('%02d %s: %s',$round,$name,$e->getMessage());fwrite(STDERR,sprintf("FAIL REVIEW %02d %s: %s\n",$round,$name,$e->getMessage()));}
};
$read=static fn(string $path):string=>(string)file_get_contents($root.'/'.$path);

$test(34,'event and audit hash chains serialize each aggregate before reading previous hash',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    assert(str_contains($repo,"acquireWorkerLease('event_chain', \$chainId)"));
    assert(str_contains($repo,"releaseWorkerLease('event_chain', \$chainId)"));
    assert(str_contains($repo,"acquireWorkerLease('audit_chain', \$chainId)"));
    assert(str_contains($repo,"releaseWorkerLease('audit_chain', \$chainId)"));
    $eventLock=strpos($repo,"acquireWorkerLease('event_chain', \$chainId)");
    $eventPrevious=strpos($repo,"SELECT event_hash FROM {\$this->tables['events']}",$eventLock);
    assert($eventLock!==false && $eventPrevious!==false && $eventLock<$eventPrevious);
    $auditLock=strpos($repo,"acquireWorkerLease('audit_chain', \$chainId)");
    $auditPrevious=strpos($repo,"SELECT event_hash FROM {\$this->tables['audit']}",$auditLock);
    assert($auditLock!==false && $auditPrevious!==false && $auditLock<$auditPrevious);
});

if($failures!==[]){fwrite(STDERR,implode("\n",$failures)."\n");exit(1);}
fwrite(STDOUT,"CF-02 sequential review register passed through R34.\n");
'''
write(test_path,test)
print('R34 corrections materialized')
