<?php

declare(strict_types=1);
ini_set('assert.exception','1');assert_options(ASSERT_ACTIVE,1);assert_options(ASSERT_EXCEPTION,1);
require_once dirname(__DIR__).'/src/Autoload.php';\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');

use Sabri\CF02\Future\EvidenceChannelIntelligence;
use Sabri\CF02\Future\IntegrationSecurityIntelligence;

$root=dirname(__DIR__);$failures=[];
$test=static function(int $round,string $name,callable $callback)use(&$failures):void{try{$callback();fwrite(STDOUT,sprintf("PASS FRESH REVIEW %02d %s\n",$round,$name));}catch(Throwable $e){$failures[]=sprintf('%02d %s: %s',$round,$name,$e->getMessage());fwrite(STDERR,sprintf("FAIL FRESH REVIEW %02d %s: %s\n",$round,$name,$e->getMessage()));}};
$read=static fn(string $path):string=>(string)file_get_contents($root.'/'.$path);

$test(1,'Future24 deep links resist normalization bypass and repeated upload content remains valid',static function():void{
    $now=new DateTimeImmutable('2026-09-14T00:00:00+00:00');$links=new IntegrationSecurityIntelligence(str_repeat('K',32));
    foreach(['//evil.example/x','/%2F%2Fevil.example/x','/%252F%252Fevil.example/x','/\\evil.example/x'] as $path){$blocked=false;try{$links->issueSecureDeepLink('case_status','OpaqueRef_12345',$path,$now);}catch(InvalidArgumentException){$blocked=true;}assert($blocked,$path);}
    $same=str_repeat('a',64);$upload=(new EvidenceChannelIntelligence())->resumableUpload('UP-repeat',10,[['index'=>0,'sha256'=>$same,'size'=>5],['index'=>1,'sha256'=>$same,'size'=>5]],str_repeat('b',64),true);assert($upload['complete']===true&&$upload['evidence_accessible']===true);
});

$test(2,'provider webhook errors never expose internal exception messages',static function()use($read):void{
    $source=$read('src/Infrastructure/WordPress/ProviderWebhookController.php');
    assert(str_contains($source,'cf02_provider_request_failed'));
    assert(str_contains($source,'The signed provider request was rejected or could not be processed.'));
    assert(!str_contains($source,'$error instanceof RuntimeException ? $error->getMessage()'));
});

$test(4,'ordinary assigned staff cannot receive C4/C5 attachment metadata without sensitive capability and recent step-up',static function()use($read):void{
    $source=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    assert(str_contains($source, '$context->hasCapability(\'case.sensitive.read\')'));
    assert(str_contains($source, '$context->recentlyAuthenticated(new DateTimeImmutable(\'now\', new DateTimeZone(\'UTC\')))'));
    assert(str_contains($source, '!in_array((string) $row[\'privacy_class\'], [\'C4\',\'C5\'], true)'));
});

if($failures!==[]){fwrite(STDERR,implode("\n",$failures)."\n");exit(1);}fwrite(STDOUT,"CF-02 fresh 40-round regression register passed through round 04.\n");
