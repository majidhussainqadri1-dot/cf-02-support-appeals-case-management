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
if($failures!==[]){exit(1);}fwrite(STDOUT,"CF-02 new ten-review register passed through R36.\n");
