<?php
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

$test(37,'sensitive case projections require the active assignment to carry the same JIT scope',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    assert(str_contains($repo,'private function activeAssignmentHasScope('));
    assert(substr_count($repo,"activeAssignmentHasScope(\$caseId, \$context, 'case.sensitive.read')")>=2);
    assert(str_contains($repo,"activeAssignmentHasScope(\$caseId, \$context, 'case.specialist.read')"));
    assert(str_contains($repo,"WHERE case_uuid=%s AND agent_ref=%s AND ended_at IS NULL"));
});

$test(38,'appeal queue readers receive queue metadata but not the evidence dossier unless appellant or assigned reviewer',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    assert(str_contains($repo,'$appellantAccess = hash_equals'));
    assert(str_contains($repo,'$assignedReviewerAccess = is_string'));
    assert(str_contains($repo,"return ['appeal' => \$appeal, 'dossier' => null];"));
    assert(str_contains($repo,"hasAnyCapability('appeal.review', 'appeal.decision', 'appeal.native.request', 'appeal.implementation.confirm')"));
});

$test(40,'quality rubric compares normalized key sets instead of rejecting a complete valid rubric by ordering',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    $posRequired=strpos($repo,'$required = [\'accuracy\',\'accessibility\',\'compliance\',\'empathy\',\'security\'];');
    $posSort=strpos($repo,'sort($required);',$posRequired);
    $posKeys=strpos($repo,'$keys = array_keys($scores);',$posRequired);
    assert($posRequired!==false && $posSort!==false && $posKeys!==false && $posRequired<$posSort && $posSort<$posKeys);
});

$test(41,'feedback replay returns the same minimized public projection and never exposes pseudonym hash or encrypted comment fields',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    assert(str_contains($repo,"return array_intersect_key(\$existing, array_flip(['case_uuid','rating','opted_out','submitted_at']));"));
    $start=strpos($repo,'public function addFeedback(');
    $end=strpos($repo,'public function mergeCases(',$start);
    $block=substr($repo,$start,$end-$start);
    assert(!str_contains($block,'return $existing;'));
});

$test(42,'quality review rejects the active case owner or active assigned handler',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    $start=strpos($repo,'public function recordQuality(');
    $end=strpos($repo,'public function qualitySample(',$start);
    $block=substr($repo,$start,$end-$start);
    assert(str_contains($block,"WHERE case_uuid=%s AND agent_ref=%s AND ended_at IS NULL"));
    assert(str_contains($block,"Quality reviewer must be independent from active case handling."));
    assert(str_contains($block,"hash_equals((string) \$case['owner_ref'], \$context->actorReference())"));
});

$test(43,'requester reply events use case ownership or verified representation rather than a user-prefix heuristic',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    $start=strpos($repo,'public function appendMessage(');
    $end=strpos($repo,'public function linkObject(',$start);
    $block=substr($repo,$start,$end-$start);
    assert(!str_contains($block,"str_starts_with(\$context->actorReference(), 'user:')"));
    assert(str_contains($block,"hash_equals((string) \$case['requester_ref'], \$context->actorReference())"));
    assert(str_contains($block,"\$context->represents((string) \$case['requester_ref'])"));
});

$test(44,'retention purge completion evidence is written inside the same transaction before canonical deletion',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    $start=strpos($repo,'public function purgeCase(');
    $end=strpos($repo,'public function dueRetention(',$start);
    $block=substr($repo,$start,$end-$start);
    $tx=strpos($block,'$this->transaction(');
    $event=strpos($block,"'SupportRetentionPurgeCompleted'");
    $delete=strpos($block,"$this->wpdb->delete($this->tables['cases']");
    assert($tx!==false && $event!==false && $delete!==false && $tx<$event && $event<$delete);
    assert(!str_contains(substr($block,$delete),'$this->appendEvent('));
});
if($failures!==[]){exit(1);}fwrite(STDOUT,"CF-02 new ten-review register passed through R44.\n");
