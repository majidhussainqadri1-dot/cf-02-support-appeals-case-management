<?php

declare(strict_types=1);
ini_set('assert.exception','1');assert_options(ASSERT_ACTIVE,1);assert_options(ASSERT_EXCEPTION,1);
require_once dirname(__DIR__).'/src/Autoload.php';\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');

use Sabri\CF02\Future\AssistanceIntelligence;
use Sabri\CF02\Future\ProblemKnowledgeIntelligence;
use Sabri\CF02\Future\AppealIntelligence;
use Sabri\CF02\Future\EvidenceChannelIntelligence;
use Sabri\CF02\Future\ContinuityExperienceIntelligence;
use Sabri\CF02\Future\IntegrationSecurityIntelligence;
use Sabri\CF02\Future\OperationsTransparencyIntelligence;

$failures=[];$test=static function(string $n,callable $c)use(&$failures):void{try{$c();fwrite(STDOUT,"PASS {$n}\n");}catch(Throwable $e){$failures[]=$n.': '.$e->getMessage();fwrite(STDERR,"FAIL {$n}: {$e->getMessage()}\n");}};
$expectInvalid=static function(callable $c):void{$blocked=false;try{$c();}catch(InvalidArgumentException){$blocked=true;}assert($blocked);};

$test('Review1 privilege signals cannot enter assistance routing',static function()use($expectInvalid):void{
    $svc=new AssistanceIntelligence();$now=new DateTimeImmutable('2026-09-08T00:00:00+00:00');
    foreach(['donor_status','donation_amount','popularity','ranking_signal','rank','reach','badge','support_priority_override'] as $field){$expectInvalid(static fn()=> $svc->caseRiskRadar([$field=>'x'],$now));}
});

$test('Review1 pre-ticket and copilot reject secrets and divert emergency',static function()use($expectInvalid):void{
    $svc=new AssistanceIntelligence();
    $expectInvalid(static fn()=> $svc->supportCopilot(['summary'=>'password: Xy!123456','category'=>'technical']));
    $expectInvalid(static fn()=> $svc->selfServiceResolution('otp: 123456',[]));
    $out=$svc->selfServiceResolution('possible heart attack',[]);assert($out['divert_to_governed_intake']===true);assert($out['resolved']===false);
});

$test('Review1 problem and appeal evidence cannot absorb native truth or secrets',static function()use($expectInvalid):void{
    $p=new ProblemKnowledgeIntelligence();$expectInvalid(static fn()=> $p->problemFingerprint('portal','technical',['password: Xy!123456']));
    $a=new AppealIntelligence();$hash=str_repeat('a',64);$expectInvalid(static fn()=> $a->evidenceRoom('APP-1',[['owner'=>'File09','type'=>'decision','reference'=>'x','version'=>'1','snapshot_hash'=>$hash,'domain_truth'=>['x'=>1]]]));
});

$test('Review1 co-browsing refuses remote control credential capture and unsafe selectors',static function()use($expectInvalid):void{
    $svc=new EvidenceChannelIntelligence();$now=new DateTimeImmutable('2026-09-08T00:00:00+00:00');$exp=$now->modify('+5 minutes');
    $expectInvalid(static fn()=> $svc->coBrowsingSession('S',true,['#ok'],$now,$exp,true,false));
    $expectInvalid(static fn()=> $svc->coBrowsingSession('S',true,['#ok'],$now,$exp,false,true));
    $expectInvalid(static fn()=> $svc->coBrowsingSession('S',true,['input[name=otp]'],$now,$exp));
});

$test('Review1 public status rejects case identity and PWA refuses sensitive offline cache',static function()use($expectInvalid):void{
    $svc=new ContinuityExperienceIntelligence();
    $expectInvalid(static fn()=> $svc->publicStatus(['incident_id'=>'I','service_key'=>'support','status'=>'open','public_summary'=>'Delay','next_update_at'=>'soon','case_id'=>'CASE-1']));
    foreach(['C4','C5'] as $class){$out=$svc->lowBandwidthDraft('sensitive',$class,false);assert($out['offline_cache_allowed']===false);}
});

$test('Review1 secure links reject absolute/open-redirect paths and expire',static function()use($expectInvalid):void{
    $svc=new IntegrationSecurityIntelligence(str_repeat('K',32));$now=new DateTimeImmutable('2026-09-08T00:00:00+00:00');
    $expectInvalid(static fn()=> $svc->issueSecureDeepLink('case_status','OpaqueRef_12345','https://evil.example/x',$now));
    $expectInvalid(static fn()=> $svc->issueSecureDeepLink('case_status','OpaqueRef_12345','//evil.example/x',$now));
    $out=$svc->issueSecureDeepLink('case_status','OpaqueRef_12345','/support/case',$now,60);assert(!$svc->verifySecureDeepLink('case_status','/support/case',$out['token'],$now->modify('+61 seconds')));
});

$test('Review1 institutional API rejects unknown scopes and short signing keys',static function()use($expectInvalid):void{
    $expectInvalid(static fn()=> new IntegrationSecurityIntelligence('short'));
    $svc=new IntegrationSecurityIntelligence(str_repeat('S',32));$now=new DateTimeImmutable('2026-09-08T00:00:00+00:00');
    $expectInvalid(static fn()=> $svc->institutionalApiPolicy('client',['admin.all'],'IdempotencyKey_12345','{}',$now,'NonceValue_123456'));
});

$test('Review1 training remains synthetic and transparency suppresses low volume',static function()use($expectInvalid):void{
    $svc=new OperationsTransparencyIntelligence();$expectInvalid(static fn()=> $svc->trainingScenario(['scenario_id'=>'SIM','category'=>'x','expected_route'=>'x','expected_guardrail'=>'x','synthetic'=>false]));
    $out=$svc->transparencyCenter('2026-08',['case_count'=>5],['cohort_size'=>50,'metrics'=>[]],['cohort_size'=>50,'metrics'=>[]],20);assert($out['status']==='suppressed');
});

if($failures!==[])exit(1);fwrite(STDOUT,"CF-02 C2-M Future24 corrective Review 1 passed.\n");
