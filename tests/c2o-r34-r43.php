<?php

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
