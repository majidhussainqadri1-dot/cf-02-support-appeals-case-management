<?php

declare(strict_types=1);
ini_set('assert.exception','1');assert_options(ASSERT_ACTIVE,1);assert_options(ASSERT_EXCEPTION,1);
require_once dirname(__DIR__).'/src/Autoload.php';\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');

use Sabri\CF02\Appeal\ReviewerAssignmentPolicy;
use Sabri\CF02\Appeal\ReviewerProfile;
use Sabri\CF02\Governance\AdverseDecisionNotice;
use Sabri\CF02\Governance\ServiceEqualityPolicy;
use Sabri\CF02\Governance\SupportParityAudit;
use Sabri\CF02\Intake\GuestContinuationToken;
use Sabri\CF02\Intake\GuestIntakePolicy;
use Sabri\CF02\Intake\IntakeChannel;
use Sabri\CF02\Intake\IntakeRequest;
use Sabri\CF02\Intake\TriagePolicy;
use Sabri\CF02\Resolution\ResolutionCode;
use Sabri\CF02\Resolution\ResolutionDecision;
use Sabri\CF02\Resolution\ResolutionPolicy;
use Sabri\CF02\Domain\CaseState;
use Sabri\CF02\Safety\EmergencyRunbookRegistry;

$failures=[];$test=static function(string $n,callable $c)use(&$failures):void{try{$c();fwrite(STDOUT,"PASS {$n}\n");}catch(Throwable $e){$failures[]=$n.': '.$e->getMessage();fwrite(STDERR,"FAIL {$n}: {$e->getMessage()."\n";} }};
$root=dirname(__DIR__);

$test('CEN-01 routing consumes severity harm deadline competence and rejects privilege signals',static function():void{
    $now=new DateTimeImmutable('2026-09-08T00:00:00+00:00');
    $request=new IntakeRequest('user:101',IntakeChannel::Web,'msg-001',true,'technical','Technical issue affecting access',['route'=>'/support','device_context'=>'desktop','reproduction_steps'=>'repeat','harm_level'=>'high','deadline_at'=>'2026-09-08T03:00:00+00:00','domain_competence'=>'technical_support'],'medium','normal','ur-PK');
    $decision=(new TriagePolicy())->decideAt($request,$now);
    assert($decision->priority()==='P1');
    assert($decision->specialistRequired());
    foreach(['donor_status','popularity','ranking_signal','rank','reach','badge'] as $field){$blocked=false;try{ServiceEqualityPolicy::assertNoPrivilegeSignals([$field=>'x']);}catch(InvalidArgumentException){$blocked=true;}assert($blocked);}
});

$test('CEN-02 linked-domain runtime persists typed reference/version plus projection hash only',static function()use($root):void{
    $source=file_get_contents($root.'/src/Infrastructure/WordPress/OperationsRepository.php');
    assert(str_contains($source,"'projection_hash' => \$projectionHash"));
    assert(str_contains($source,"'object_version' => \$objectVersion"));
    assert(!str_contains($source,"'projection_json'"));
});

$test('CEN-03 adverse decision notice carries reason policy evidence remedy appeal and deadline',static function():void{
    $issued=new DateTimeImmutable('2026-09-08T00:00:00+00:00');
    $notice=new AdverseDecisionNotice('DEC-1','Policy condition was not met.','policy-2.4',['Evidence reference E-1'],'Correct the stated condition and resubmit.','/support/appeals/DEC-1',$issued->modify('+14 days'),$issued,'ur-PK');
    $projection=$notice->projection();
    foreach(['reason','policy_version','evidence_summary','remedy','appeal_route','appeal_deadline','notice_hash'] as $field){assert(isset($projection[$field]));}
    assert(strlen($projection['notice_hash'])===64);
});

$test('CEN-04 appeal reviewer is capability competent conflict-free and organizationally separate',static function():void{
    $same=new ReviewerProfile('reviewer:same',['moderation'],[],[],true,true,'moderation_unit');
    $independent=new ReviewerProfile('reviewer:independent',['moderation'],[],[],true,true,'appeals_unit');
    $result=(new ReviewerAssignmentPolicy())->assign('DEC-2','actor:maker','moderation',true,[$same,$independent],'moderation_unit');
    assert($result['reviewer_reference']==='reviewer:independent');
});

$test('CEN-05 has six separate public-safe emergency runbook boundaries',static function():void{
    assert(EmergencyRunbookRegistry::types()===['clinical_red_flag','imminent_harm','account_takeover','child_safety','privacy_breach','financial_fraud']);
    foreach(EmergencyRunbookRegistry::types() as $type){$runbook=EmergencyRunbookRegistry::forType($type);assert($runbook['ordinary_sla']===false);assert($runbook['auto_close']===false);}
    assert(EmergencyRunbookRegistry::classifyText('I think this is a heart attack')==='clinical_red_flag');
});

$test('CEN-08 guest intake is low-sensitivity only and continuation is encrypted time-bounded',static function():void{
    $safe=GuestIntakePolicy::normalize(['category'=>'technical','subject'=>'Public page is not loading','description'=>'The public support page shows an error.','impact'=>'single_action','urgency'=>'normal','locale'=>'ur-PK']);
    $key=str_repeat('k',SODIUM_CRYPTO_SECRETBOX_KEYBYTES);$tokens=new GuestContinuationToken($key);$now=new DateTimeImmutable('2026-09-08T00:00:00+00:00');$token=$tokens->issue($safe,$now,600);
    assert(str_starts_with($token,'CF02G1.'));assert(!str_contains($token,'Public page'));
    $verified=$tokens->verify($token,$now->modify('+5 minutes'));assert($verified['intake']['category']==='technical');
    $blocked=false;try{GuestIntakePolicy::normalize(['category'=>'privacy_data_rights','subject'=>'Privacy request','description'=>'Need private data','impact'=>'single_action','urgency'=>'normal','locale'=>'ur-PK']);}catch(InvalidArgumentException){$blocked=true;}assert($blocked);
});

$test('CEN-09 failed File-19-style outcome delivery blocks false resolution/auto-close',static function():void{
    $now=new DateTimeImmutable('2026-09-08T00:00:00+00:00');
    $decision=new ResolutionDecision(ResolutionCode::UserActionCompleted,['Verified outcome'],null,'Follow the verified steps.',true,$now->modify('+7 days'),true,true);
    $reasons=(new ResolutionPolicy())->validate(CaseState::InProgress,$decision,[],[],$now,'dead_letter');
    assert($reasons!==[]);assert(str_contains(implode(' ',$reasons),'delivery'));
});

$test('CEN-10 monthly donor/non-donor parity audit is aggregate-only privacy-thresholded and blocking',static function():void{
    $metrics=['first_response_seconds_p50'=>100.0,'resolution_seconds_p50'=>500.0,'escalation_rate'=>0.10,'reopen_rate'=>0.05,'appeal_access_rate'=>0.20];
    $pass=(new SupportParityAudit())->evaluate('2026-08',['cohort_size'=>50,'metrics'=>$metrics],['cohort_size'=>60,'metrics'=>$metrics]);assert($pass['status']==='pass');assert($pass['routing_consumes_donor_signal']===false);
    $bad=$metrics;$bad['first_response_seconds_p50']=200.0;$blocked=(new SupportParityAudit())->evaluate('2026-08',['cohort_size'=>50,'metrics'=>$bad],['cohort_size'=>60,'metrics'=>$metrics]);assert($blocked['status']==='blocker');
    $suppressed=(new SupportParityAudit())->evaluate('2026-08',['cohort_size'=>10,'metrics'=>$metrics],['cohort_size'=>60,'metrics'=>$metrics]);assert($suppressed['status']==='suppressed');
});

$test('guest continuation and monthly parity are wired into active runtime/scheduler',static function()use($root):void{
    $runtime=file_get_contents($root.'/src/Infrastructure/WordPress/Runtime.php');$scheduler=file_get_contents($root.'/src/Infrastructure/WordPress/Scheduler.php');
    assert(str_contains($runtime,'GuestIntakeController'));assert(str_contains($runtime,'GuestContinuationToken'));
    assert(str_contains($scheduler,'HOOK_SUPPORT_PARITY'));assert(str_contains($scheduler,'MonthlyParityAuditRunner::run'));
});

if($failures!==[])exit(1);fwrite(STDOUT,"All CF-02 C2-L latest-two-plans completion tests passed.\n");
