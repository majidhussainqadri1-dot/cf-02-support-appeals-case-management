from pathlib import Path
ROOT=Path('.')
def read(p): return (ROOT/p).read_text()
def write(p,s): (ROOT/p).write_text(s)
p='src/Infrastructure/WordPress/OperationsRepository.php'
s=read(p)
s=s.replace(
"            $this->transaction(function () use ($messageId, $caseId, $context, $visibility, $channel, $ciphertext, $contentHash, $idempotencyKey, $purpose, $at): void {",
"            $this->transaction(function () use ($messageId, $caseId, $case, $context, $visibility, $channel, $ciphertext, $contentHash, $idempotencyKey, $purpose, $at): void {",1)
old="""                $event = $visibility === 'requester' && str_starts_with($context->actorReference(), 'user:')
                    ? 'SupportUserReplied' : 'SupportAgentReplied';
"""
new="""                $requesterActor = hash_equals((string) $case['requester_ref'], $context->actorReference())
                    || $context->represents((string) $case['requester_ref']);
                $event = $visibility === 'requester' && $requesterActor
                    ? 'SupportUserReplied' : 'SupportAgentReplied';
"""
if old not in s: raise SystemExit('R43 requester event classification block missing')
s=s.replace(old,new,1)
write(p,s)
tp='tests/c2q-r36-r45.php'
t=read(tp); needle='if($failures!==[]){exit(1);}'
block=r'''
$test(43,'requester reply events use case ownership or verified representation rather than a user-prefix heuristic',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    $start=strpos($repo,'public function appendMessage(');
    $end=strpos($repo,'public function linkObject(',$start);
    $block=substr($repo,$start,$end-$start);
    assert(!str_contains($block,"str_starts_with(\$context->actorReference(), 'user:')"));
    assert(str_contains($block,"hash_equals((string) \$case['requester_ref'], \$context->actorReference())"));
    assert(str_contains($block,"\$context->represents((string) \$case['requester_ref'])"));
});
'''
if needle not in t: raise SystemExit('R43 test marker missing')
t=t.replace(needle,block+needle,1).replace('passed through R42','passed through R43',1)
write(tp,t)
print('R43 correction materialized')
