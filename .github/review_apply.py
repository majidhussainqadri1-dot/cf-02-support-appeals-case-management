from pathlib import Path
ROOT=Path('.')
def read(p): return (ROOT/p).read_text()
def write(p,s): (ROOT/p).write_text(s)
p='src/Infrastructure/WordPress/OperationsRepository.php'
s=read(p)
old="""        $this->caseForActor($caseId, $context);
        $required = ['accuracy','accessibility','compliance','empathy','security'];
"""
new="""        $case = $this->caseForActor($caseId, $context);
        $activeAssignment = $this->value($this->wpdb->prepare(
            \"SELECT COUNT(*) FROM {$this->tables['assignments']}
             WHERE case_uuid=%s AND agent_ref=%s AND ended_at IS NULL\",
            $caseId->value(), $context->actorReference()
        ));
        if (($case['owner_ref'] !== null && hash_equals((string) $case['owner_ref'], $context->actorReference()))
            || (int) $activeAssignment > 0) {
            throw new RuntimeException('Quality reviewer must be independent from active case handling.');
        }
        $required = ['accuracy','accessibility','compliance','empathy','security'];
"""
if old not in s: raise SystemExit('R42 recordQuality preamble missing')
s=s.replace(old,new,1)
write(p,s)
tp='tests/c2q-r36-r45.php'
t=read(tp); needle='if($failures!==[]){exit(1);}'
block=r'''
$test(42,'quality review rejects the active case owner or active assigned handler',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    $start=strpos($repo,'public function recordQuality(');
    $end=strpos($repo,'public function qualitySample(',$start);
    $block=substr($repo,$start,$end-$start);
    assert(str_contains($block,"WHERE case_uuid=%s AND agent_ref=%s AND ended_at IS NULL"));
    assert(str_contains($block,"Quality reviewer must be independent from active case handling."));
    assert(str_contains($block,"hash_equals((string) \$case['owner_ref'], \$context->actorReference())"));
});
'''
if needle not in t: raise SystemExit('R42 test marker missing')
t=t.replace(needle,block+needle,1).replace('passed through R41','passed through R42',1)
write(tp,t)
print('R42 correction materialized')
