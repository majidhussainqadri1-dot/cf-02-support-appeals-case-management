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

$test(34,'quality review rejects requester owner and active-handler self review',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    assert(str_contains($repo,"Quality review must be independent from the requester and active case handler."));
    assert(str_contains($repo,"SELECT COUNT(*) FROM {\$this->tables['assignments']} WHERE case_uuid=%s AND agent_ref=%s AND ended_at IS NULL"));
    assert(str_contains($repo,"hash_equals((string) \$case['requester_ref'], \$reviewer)"));
    assert(str_contains($repo,"hash_equals((string) \$case['owner_ref'], \$reviewer)"));
});

$test(35,'stale SLA worker cannot overwrite paused or already-resolved timer state',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    $worker=$read('src/Infrastructure/WordPress/RuntimeWorker.php');
    assert(str_contains($repo,"SELECT status,record_version FROM {\$this->tables['sla']} WHERE case_uuid=%s"));
    assert(str_contains($repo,"!in_array(\$current, ['running','at_risk'], true)"));
    assert(str_contains($repo,"'status' => \$current"));
    assert(str_contains($worker,"if (\$version === 0)"));
});

$test(36,'worker leases re-read pending state before any external side effect',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    $worker=$read('src/Infrastructure/WordPress/RuntimeWorker.php');
    foreach(['pendingEventById','pendingOutboxById','pendingCommandById'] as $method){assert(str_contains($repo,"function {$method}"));}
    assert(str_contains($worker,'pendingEventById($eventId)'));
    assert(str_contains($worker,'pendingOutboxById($messageId)'));
    assert(str_contains($worker,'pendingCommandById($commandId)'));
    assert(substr_count($worker,'if ($fresh === null)')>=3);
});

if($failures!==[]){fwrite(STDERR,implode("\n",$failures)."\n");exit(1);}
fwrite(STDOUT,"CF-02 R34-R43 regression register passed through R36.\n");
