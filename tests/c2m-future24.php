<?php

declare(strict_types=1);
ini_set('assert.exception','1');
assert_options(ASSERT_ACTIVE,1);
assert_options(ASSERT_EXCEPTION,1);
require_once dirname(__DIR__).'/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');

use Sabri\CF02\Future\FeatureCatalog;
use Sabri\CF02\Future\AssistanceIntelligence;
use Sabri\CF02\Future\ProblemKnowledgeIntelligence;
use Sabri\CF02\Future\AppealIntelligence;
use Sabri\CF02\Future\EvidenceChannelIntelligence;
use Sabri\CF02\Future\ContinuityExperienceIntelligence;
use Sabri\CF02\Future\IntegrationSecurityIntelligence;
use Sabri\CF02\Future\OperationsTransparencyIntelligence;

$failures=[];
$test=static function(string $name,callable $callback)use(&$failures):void{
    try{$callback();fwrite(STDOUT,"PASS {$name}\n");}
    catch(Throwable $e){$failures[]=$name.': '.$e->getMessage();fwrite(STDERR,"FAIL {$name}: {$e->getMessage()}\n");}
};

$test('Future24 catalog is stable complete and feature-gated',static function():void{
    assert(FeatureCatalog::VERSION==='1.0');
    assert(FeatureCatalog::count()===24);
    $ids=array_keys(FeatureCatalog::all());
    assert($ids===array_map(static fn(int $n):string=>sprintf('CF02-FUT-%03d',$n),range(1,24)));
    foreach(FeatureCatalog::all() as $feature){assert($feature['activation']==='feature-gated');assert(trim($feature['guardrail'])!=='');}
});

$test('FUT-001 support copilot is suggestion-only and detects missing fields',static function():void{
    $out=(new AssistanceIntelligence())->supportCopilot(['summary'=>'Cannot access account settings','category'=>'technical','required_fields'=>['route','device'],'fields'=>['route'=>'/settings']]);
    assert($out['feature_id']==='CF02-FUT-001');assert($out['missing_information']===['device']);assert($out['final_decision_allowed']===false);assert($out['human_review_required']);
});

$test('FUT-002 self service diverts emergencies and preserves intake',static function():void{
    $svc=new AssistanceIntelligence();
    $safe=$svc->selfServiceResolution('The page cache looks stale',[['match'=>'cache','step'=>'Clear the site cache and retry.']]);
    assert($safe['feature_id']==='CF02-FUT-002');assert($safe['case_creation_remains_available']);assert($safe['steps']!==[]);
    $urgent=$svc->selfServiceResolution('I think this is a heart attack',[]);
    assert($urgent['divert_to_governed_intake']);assert($urgent['emergency_type']==='clinical_red_flag');
});

$test('FUT-003 risk radar uses service risk and rejects privilege signals',static function():void{
    $svc=new AssistanceIntelligence();$now=new DateTimeImmutable('2026-09-08T00:00:00+00:00');
    $out=$svc->caseRiskRadar(['deadline_at'=>'2026-09-08T00:30:00+00:00','transfer_count'=>5,'reopen_count'=>1,'provider_failure_count'=>1,'user_waiting'=>true],$now);
    assert($out['feature_id']==='CF02-FUT-003');assert($out['risk_score']>=45);assert($out['privilege_signals_consumed']===false);
    $blocked=false;try{$svc->caseRiskRadar(['donor_status'=>'vip'],$now);}catch(InvalidArgumentException){$blocked=true;}assert($blocked);
});

$test('FUT-004 next-best-action never self-executes',static function():void{
    $out=(new AssistanceIntelligence())->nextBestAction(['state'=>'in_progress','native_owner_action_required'=>true]);
    assert($out['feature_id']==='CF02-FUT-004');assert($out['action']==='issue_native_owner_command');assert($out['authorized_to_execute']===false);
});

$test('FUT-005 problem center creates deterministic privacy-safe fingerprint',static function():void{
    $svc=new ProblemKnowledgeIntelligence();
    $a=$svc->problemFingerprint('portal','technical',['Timeout','Blank page'],'Cache stampede','Purge cache','1.0.1');
    $b=$svc->problemFingerprint('portal','technical',['Blank page','Timeout'],'Cache stampede','Purge cache','1.0.1');
    assert($a['feature_id']==='CF02-FUT-005');assert($a['problem_fingerprint']===$b['problem_fingerprint']);assert(str_contains($a['case_link_semantics'],'remain separate'));
});

$test('FUT-006 knowledge-gap detector is thresholded and human-approved',static function():void{
    $gaps=(new ProblemKnowledgeIntelligence())->knowledgeGaps(['login.help'=>12,'billing.help'=>3],['login.help'=>false,'billing.help'=>false],5);
    assert(count($gaps)===1);assert($gaps[0]['feature_id']==='CF02-FUT-006');assert($gaps[0]['topic']==='login.help');assert($gaps[0]['publication_requires_human_approval']);
});

$test('FUT-007 appeal preview is explicitly non-binding',static function():void{
    $d=new DateTimeImmutable('2026-09-01T00:00:00+00:00');$s=$d->modify('+2 days');
    $out=(new AppealIntelligence())->eligibilityPreview('DEC-1','user:1',true,$d,$s,14,['policy_misapplied'],false);
    assert($out['feature_id']==='CF02-FUT-007');assert($out['eligible_preview']);assert($out['binding']===false);
});

$test('FUT-008 appeal evidence room stores typed references not domain truth',static function():void{
    $hash=str_repeat('a',64);
    $out=(new AppealIntelligence())->evidenceRoom('APP-1',[['owner'=>'File09','type'=>'decision','reference'=>'mod:123','version'=>'7','snapshot_hash'=>$hash]]);
    assert($out['feature_id']==='CF02-FUT-008');assert($out['raw_native_truth_copied']===false);
    $blocked=false;try{(new AppealIntelligence())->evidenceRoom('APP-1',[['owner'=>'File09','type'=>'decision','reference'=>'mod:123','version'=>'7','snapshot_hash'=>$hash,'raw_payload'=>'secret']]);}catch(InvalidArgumentException){$blocked=true;}assert($blocked);
});

$test('FUT-009 decision difference viewer redacts credential material',static function():void{
    $out=(new AppealIntelligence())->decisionDifference(['outcome'=>'deny','reason'=>'password: Xy!123456','policy_version'=>'1'],['outcome'=>'allow','reason'=>'Evidence corrected the record.','policy_version'=>'2']);
    assert($out['feature_id']==='CF02-FUT-009');assert($out['outcome_changed']);assert($out['original']['reason']==='[redacted-sensitive-material]');
});

$test('FUT-010 second-level complaint rejects same handler as reviewer',static function():void{
    $svc=new AppealIntelligence();
    $bad=$svc->serviceComplaint('CASE-1','agent:1','agent:1','Unjustified closure',true,true);assert($bad['accepted']===false);
    $ok=$svc->serviceComplaint('CASE-1','agent:1','reviewer:2','Unjustified closure',true,true);assert($ok['feature_id']==='CF02-FUT-010');assert($ok['accepted']);
});

$test('FUT-011 co-browsing requires scoped consent and blocks credential capture',static function():void{
    $svc=new EvidenceChannelIntelligence();$now=new DateTimeImmutable('2026-09-08T00:00:00+00:00');
    $out=$svc->coBrowsingSession('CBS-1',true,['#support-form','.error-panel'],$now,$now->modify('+10 minutes'));
    assert($out['feature_id']==='CF02-FUT-011');assert($out['credential_capture']===false);
    $blocked=false;try{$svc->coBrowsingSession('CBS-2',true,['#password'],$now,$now->modify('+10 minutes'));}catch(InvalidArgumentException){$blocked=true;}assert($blocked);
});

$test('FUT-012 voice support preserves File 17 ownership',static function():void{
    $out=(new EvidenceChannelIntelligence())->voiceSupportHandoff('file17:conv:1','CASE-1',true,str_repeat('b',64));
    assert($out['feature_id']==='CF02-FUT-012');assert($out['communication_owner']==='File 17');
});

$test('FUT-013 evidence sanitizer strips metadata and warns on secrets',static function():void{
    $out=(new EvidenceChannelIntelligence())->sanitizeEvidenceEnvelope('../scan.pdf','password: Xy!123456',['mime_type'=>'application/pdf','author'=>'Alice','gps'=>'1,2']);
    assert($out['feature_id']==='CF02-FUT-013');assert($out['filename']==='scan.pdf');assert(in_array('author',$out['removed_metadata_keys'],true));assert(!$out['upload_allowed']);
});

$test('FUT-014 resumable evidence upload remains inaccessible before scan pass',static function():void{
    $chunks=[['index'=>0,'sha256'=>str_repeat('1',64),'size'=>5],['index'=>1,'sha256'=>str_repeat('2',64),'size'=>5]];
    $out=(new EvidenceChannelIntelligence())->resumableUpload('UP-1',10,$chunks,str_repeat('3',64),false);
    assert($out['feature_id']==='CF02-FUT-014');assert($out['complete']);assert($out['evidence_accessible']===false);
});

$test('FUT-015 public status rejects private fields',static function():void{
    $svc=new ContinuityExperienceIntelligence();
    $out=$svc->publicStatus(['incident_id'=>'CF02-INC-0001','service_key'=>'support','status'=>'open','public_summary'=>'Support replies are delayed.','next_update_at'=>'2026-09-08T01:00:00+00:00']);
    assert($out['feature_id']==='CF02-FUT-015');assert($out['privacy_safe']);
    $blocked=false;try{$svc->publicStatus(['incident_id'=>'CF02-INC-0001','service_key'=>'support','status'=>'open','public_summary'=>'Delay','next_update_at'=>'x','user_id'=>'1']);}catch(InvalidArgumentException){$blocked=true;}assert($blocked);
});

$test('FUT-016 incident subscription hides other reporters',static function():void{
    $now=new DateTimeImmutable('2026-09-08T00:00:00+00:00');
    $out=(new ContinuityExperienceIntelligence())->incidentSubscription('CF02-INC-0001','push',str_repeat('c',64),$now,$now->modify('+30 days'));
    assert($out['feature_id']==='CF02-FUT-016');assert($out['other_reporters_visible']===false);
});

$test('FUT-017 multilingual support marks high-risk translation for human review',static function():void{
    $out=(new ContinuityExperienceIntelligence())->translationDraft('Please review the clinical warning.','en-US','ur-PK','clinical');
    assert($out['feature_id']==='CF02-FUT-017');assert($out['human_review_required']);assert($out['authoritative']===false);
});

$test('FUT-018 accessibility profile contains preferences not identity authority',static function():void{
    $out=(new ContinuityExperienceIntelligence())->accessibilityProfile(['preferred_locale'=>'ur-PK','screen_reader'=>true,'large_text'=>true]);
    assert($out['feature_id']==='CF02-FUT-018');assert($out['identity_authority_stored']===false);
});

$test('FUT-019 PWA low-bandwidth mode refuses offline C4/C5 cache',static function():void{
    $out=(new ContinuityExperienceIntelligence())->lowBandwidthDraft('Sensitive case draft','C4',false);
    assert($out['feature_id']==='CF02-FUT-019');assert($out['offline_cache_allowed']===false);
});

$test('FUT-020 native mobile stays on canonical CF-02 backend',static function():void{
    $out=(new ContinuityExperienceIntelligence())->mobileEnvelope('opaque-case','1.1.0','android','opaque-token');
    assert($out['feature_id']==='CF02-FUT-020');assert($out['duplicate_mobile_database']===false);
});

$test('FUT-021 secure deep links are signed expiring local-only and verifiable',static function():void{
    $svc=new IntegrationSecurityIntelligence(str_repeat('K',32));$now=new DateTimeImmutable('2026-09-08T00:00:00+00:00');
    $out=$svc->issueSecureDeepLink('case_status','OpaqueRef_12345','/support/cases/view',$now,600);
    assert($out['feature_id']==='CF02-FUT-021');assert($svc->verifySecureDeepLink('case_status','/support/cases/view',$out['token'],$now->modify('+5 minutes')));
    assert(!$svc->verifySecureDeepLink('case_status','/support/cases/view',$out['token'],$now->modify('+20 minutes')));
});

$test('FUT-022 institutional API applies scopes idempotency and signed webhook envelope',static function():void{
    $svc=new IntegrationSecurityIntelligence(str_repeat('S',32));$now=new DateTimeImmutable('2026-09-08T00:00:00+00:00');
    $out=$svc->institutionalApiPolicy('clinic.partner',['case.create','case.read'],'IdempotencyKey_12345','{"x":1}',$now,'NonceValue_123456');
    assert($out['feature_id']==='CF02-FUT-022');assert(strlen($out['webhook_signature'])===64);assert($out['replay_protection_required']);
});

$test('FUT-023 training lab is synthetic-only',static function():void{
    $svc=new OperationsTransparencyIntelligence();
    $out=$svc->trainingScenario(['scenario_id'=>'SIM-1','category'=>'account_access','expected_route'=>'account_support','expected_guardrail'=>'step_up','synthetic'=>true]);
    assert($out['feature_id']==='CF02-FUT-023');assert($out['real_data_allowed']===false);
    $blocked=false;try{$svc->trainingScenario(['scenario_id'=>'SIM-2','category'=>'x','expected_route'=>'x','expected_guardrail'=>'x','synthetic'=>true,'patient_id'=>'123']);}catch(InvalidArgumentException){$blocked=true;}assert($blocked);
});

$test('FUT-024 transparency center is aggregate thresholded and parity-aware',static function():void{
    $metrics=['case_count'=>100,'first_response_seconds_p50'=>100,'resolution_seconds_p50'=>500,'reopen_rate'=>0.05,'appeal_overturn_rate'=>0.10,'accessibility_completion_rate'=>0.95,'major_incident_count'=>1];
    $parityMetrics=['first_response_seconds_p50'=>100.0,'resolution_seconds_p50'=>500.0,'escalation_rate'=>0.10,'reopen_rate'=>0.05,'appeal_access_rate'=>0.20];
    $out=(new OperationsTransparencyIntelligence())->transparencyCenter('2026-08',$metrics,['cohort_size'=>50,'metrics'=>$parityMetrics],['cohort_size'=>60,'metrics'=>$parityMetrics]);
    assert($out['feature_id']==='CF02-FUT-024');assert($out['status']==='published-aggregate');assert($out['support_parity_status']==='pass');assert($out['individual_staff_scoring']===false);
});

if($failures!==[]){fwrite(STDERR,implode("\n",$failures)."\n");exit(1);}fwrite(STDOUT,"All CF-02 C2-M Future24 implementation tests passed.\n");
