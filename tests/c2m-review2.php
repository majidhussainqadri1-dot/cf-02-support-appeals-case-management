<?php

declare(strict_types=1);
ini_set('assert.exception','1');assert_options(ASSERT_ACTIVE,1);assert_options(ASSERT_EXCEPTION,1);
require_once dirname(__DIR__).'/src/Autoload.php';\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');
use Sabri\CF02\Future\FeatureCatalog;

$failures=[];$test=static function(string $n,callable $c)use(&$failures):void{try{$c();fwrite(STDOUT,"PASS {$n}\n");}catch(Throwable $e){$failures[]=$n.': '.$e->getMessage();fwrite(STDERR,"FAIL {$n}: {$e->getMessage()}\n");}};
$root=dirname(__DIR__);

$test('Review2 all twenty-four FUT IDs exist exactly in catalog order',static function():void{
    $ids=array_keys(FeatureCatalog::all());assert(count($ids)===24);foreach(range(1,24) as $index){assert($ids[$index-1]===sprintf('CF02-FUT-%03d',$index));}
});

$test('Review2 Future namespace is pure domain code with no direct WordPress persistence or network side effects',static function()use($root):void{
    $files=glob($root.'/src/Future/*.php');assert(is_array($files)&&count($files)===8);
    foreach($files as $file){$source=(string)file_get_contents($file);foreach(['$wpdb','update_option(','delete_option(','wp_remote_','register_rest_route(','add_action(','add_filter(','curl_exec(','file_put_contents('] as $forbidden){assert(!str_contains($source,$forbidden),basename($file).' contains direct runtime side effect '.$forbidden);}}
});

$test('Review2 security primitives and local-only deep-link boundary are permanent source gates',static function()use($root):void{
    $source=(string)file_get_contents($root.'/src/Future/IntegrationSecurityIntelligence.php');
    assert(str_contains($source,"hash_hmac('sha256'"));assert(str_contains($source,'hash_equals('));assert(str_contains($source,"str_starts_with(\$relativePath,'/')"));assert(str_contains($source,"str_starts_with(\$relativePath,'//')"));
});

$test('Review2 evidence access requires verified checksum and repeated-content chunks remain valid',static function():void{
    $svc=new \Sabri\CF02\Future\EvidenceChannelIntelligence();
    $chunks=[['index'=>0,'sha256'=>str_repeat('a',64),'size'=>5],['index'=>1,'sha256'=>str_repeat('a',64),'size'=>5]];
    $notVerified=$svc->resumableUpload('UP-R8',10,$chunks,str_repeat('b',64),true,false);
    assert($notVerified['complete']===true);assert($notVerified['checksum_verified']===false);assert($notVerified['evidence_accessible']===false);
    $verified=$svc->resumableUpload('UP-R8',10,$chunks,str_repeat('b',64),true,true);
    assert($verified['evidence_accessible']===true);
});

$test('Review2 problem knowledge rejects optional secret material',static function():void{
    $svc=new \Sabri\CF02\Future\ProblemKnowledgeIntelligence();$blocked=false;
    try{$svc->problemFingerprint('portal','technical',['timeout'],'password: Xy!123456');}catch(InvalidArgumentException){$blocked=true;}
    assert($blocked);
});

$test('Review2 institutional API scopes are non-empty unique and allowlisted',static function():void{
    $svc=new \Sabri\CF02\Future\IntegrationSecurityIntelligence(str_repeat('S',32));$now=new DateTimeImmutable('2026-09-08T00:00:00+00:00');
    foreach([[],['case.read','case.read']] as $scopes){$blocked=false;try{$svc->institutionalApiPolicy('client',$scopes,'IdempotencyKey_12345','{}',$now,'NonceValue_123456');}catch(InvalidArgumentException){$blocked=true;}assert($blocked);}
});

$test('Review2 public transparency rejects impossible metric values',static function():void{
    $svc=new \Sabri\CF02\Future\OperationsTransparencyIntelligence();$blocked=false;
    try{$svc->transparencyCenter('2026-08',['case_count'=>100,'reopen_rate'=>1.5],['cohort_size'=>50,'metrics'=>[]],['cohort_size'=>50,'metrics'=>[]]);}catch(InvalidArgumentException){$blocked=true;}
    assert($blocked);
});

$test('Review2 future catalog preserves canonical-owner and activation boundaries',static function():void{
    foreach(FeatureCatalog::all() as $feature){assert($feature['activation']==='feature-gated');assert(str_contains($feature['owner'],'CF-02')||str_contains($feature['owner'],'identity/preferences'));assert(trim($feature['guardrail'])!=='');}
});

$test('Review2 no schema or public contract fork is introduced by Future24 foundations',static function()use($root):void{
    $catalog=(string)file_get_contents($root.'/src/Future/FeatureCatalog.php');$schema=(string)file_get_contents($root.'/src/Infrastructure/WordPress/SchemaCompletion.php');$contract=(string)file_get_contents($root.'/src/Contracts/SupportContractCatalog.php');
    assert(!str_contains($catalog,'CREATE TABLE'));assert(str_contains($schema,"public const VERSION = '1.3.0';"));assert(str_contains($contract,"public const CONTRACT_VERSION = '1.3.0';"));
});

if($failures!==[])exit(1);fwrite(STDOUT,"CF-02 C2-M Future24 fresh adversarial Review 2 passed.\n");
