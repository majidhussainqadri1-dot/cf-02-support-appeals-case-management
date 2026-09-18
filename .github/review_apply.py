from pathlib import Path

ROOT=Path('.')
def read(p): return (ROOT/p).read_text()
def write(p,s): (ROOT/p).write_text(s)

repo_path='src/Infrastructure/WordPress/OperationsRepository.php'
repo=read(repo_path)

old="""            $existing = $this->row($this->wpdb->prepare(
                \"SELECT aggregate_type,aggregate_ref,payload_hash,event_hash FROM {$this->tables['events']} WHERE event_uuid=%s\",
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
"""
new="""            $existing = $this->row($this->wpdb->prepare(
                \"SELECT aggregate_type,aggregate_ref,actor_ref,purpose,payload_hash,event_hash FROM {$this->tables['events']} WHERE event_uuid=%s\",
                $eventId
            ));
            if ($existing !== null) {
                if (!hash_equals((string) $existing['aggregate_type'], $aggregateType)
                    || !hash_equals((string) $existing['aggregate_ref'], $aggregateRef)
                    || !hash_equals((string) $existing['actor_ref'], $context->actorReference())
                    || !hash_equals((string) $existing['purpose'], $purpose)
                    || !hash_equals((string) $existing['payload_hash'], $payloadHash)) {
                    throw new RuntimeException('Event idempotency collision.');
                }
                $this->ensureEventAudit($aggregateType, $aggregateRef, $context, $purpose, $eventType, $objectVersion, $payloadHash, $at);
                return $eventId;
            }
"""
if old not in repo: raise SystemExit('R36 existing-event block not found')
repo=repo.replace(old,new,1)
repo=repo.replace(
"            $this->appendAudit($aggregateType, $aggregateRef, $context, $purpose, $eventType, 'accepted', $objectVersion, $payloadHash, $at);\n            return $eventId;",
"            $this->ensureEventAudit($aggregateType, $aggregateRef, $context, $purpose, $eventType, $objectVersion, $payloadHash, $at);\n            return $eventId;",1)

marker="    private function appendAudit(\n"
helper="""    private function ensureEventAudit(
        string $objectType,
        string $objectRef,
        PrincipalContext $context,
        string $purpose,
        string $action,
        int $objectVersion,
        string $contextHash,
        DateTimeImmutable $at
    ): void {
        $existing = $this->row($this->wpdb->prepare(
            \"SELECT object_version FROM {$this->tables['audit']}
             WHERE object_type=%s AND object_ref=%s AND actor_ref=%s AND purpose=%s
               AND action_key=%s AND result_code='accepted' AND context_hash=%s
             ORDER BY id DESC LIMIT 1\",
            $objectType, $objectRef, $context->actorReference(), $purpose, $action, $contextHash
        ));
        if ($existing !== null) {
            if ((int) $existing['object_version'] !== $objectVersion) {
                throw new RuntimeException('Event replay object version differs from recorded audit evidence.');
            }
            return;
        }
        $this->appendAudit($objectType, $objectRef, $context, $purpose, $action, 'accepted', $objectVersion, $contextHash, $at);
    }

"""
idx=repo.find(marker)
if idx<0: raise SystemExit('R36 appendAudit marker not found')
repo=repo[:idx]+helper+repo[idx:]
write(repo_path,repo)

test_path='tests/c2q-r36-r45.php'
test=r'''<?php
declare(strict_types=1);
ini_set('assert.exception','1');
assert_options(ASSERT_ACTIVE,1);
assert_options(ASSERT_EXCEPTION,1);
$root=dirname(__DIR__);$failures=[];
$test=static function(int $r,string $n,callable $c)use(&$failures):void{try{$c();fwrite(STDOUT,sprintf("PASS REVIEW %02d %s\n",$r,$n));}catch(Throwable $e){$failures[]=sprintf('%02d %s: %s',$r,$n,$e->getMessage());fwrite(STDERR,end($failures)."\n");}};
$read=static fn(string $p):string=>(string)file_get_contents($root.'/'.$p);

$test(36,'event replay binds actor purpose version and repairs missing audit evidence',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    assert(str_contains($repo,'SELECT aggregate_type,aggregate_ref,actor_ref,purpose,payload_hash,event_hash'));
    assert(str_contains($repo,"hash_equals((string) \$existing['actor_ref'], \$context->actorReference())"));
    assert(str_contains($repo,"hash_equals((string) \$existing['purpose'], \$purpose)"));
    assert(str_contains($repo,'private function ensureEventAudit('));
    assert(str_contains($repo,'Event replay object version differs from recorded audit evidence.'));
    assert(substr_count($repo,'$this->ensureEventAudit(')>=2);
});
if($failures!==[]){exit(1);}fwrite(STDOUT,"CF-02 new ten-review register passed through R36.\n");
'''
write(test_path,test)
print('R36 corrections materialized')
