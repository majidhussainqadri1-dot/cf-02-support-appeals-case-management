<?php

declare(strict_types=1);
ini_set('assert.exception','1');assert_options(ASSERT_ACTIVE,1);assert_options(ASSERT_EXCEPTION,1);
require_once dirname(__DIR__).'/src/Autoload.php';\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');

use Sabri\CF02\Activation\ActivationEvidence;
use Sabri\CF02\Activation\ActivationGate;
use Sabri\CF02\Configuration\QueueDefinition;
use Sabri\CF02\Configuration\QueueRegistry;
use Sabri\CF02\Configuration\SupportCategory;
use Sabri\CF02\Configuration\SupportTaxonomy;
use Sabri\CF02\Contracts\RequiredCompanionContracts;
use Sabri\CF02\Domain\CaseState;
use Sabri\CF02\Domain\CaseStateMachine;
use Sabri\CF02\Domain\InvalidTransition;
use Sabri\CF02\Governance\ChangeControlRecord;
use Sabri\CF02\Staffing\StaffingRegistry;

$failures=[];$test=static function(string $n,callable $c)use(&$failures):void{try{$c();fwrite(STDOUT,"PASS {$n}\n");}catch(Throwable $e){$failures[]=$n.': '.$e->getMessage();fwrite(STDERR,"FAIL {$n}: {$e->getMessage()}\n");}};
$now=new DateTimeImmutable('2026-08-04T09:30:00+00:00');
$identity=['runtime_version'=>'1.0.0-rc.5','schema_version'=>'1.3.0','source_sha'=>str_repeat('a',40),'package_sha256'=>str_repeat('b',64)];
$contracts=static function()use($now):array{$out=[];foreach(RequiredCompanionContracts::definitions() as $key=>$def){if(!$def['required'])continue;$out[$key]=['ready'=>true,'enabled'=>true,'owner'=>$def['owner'],'contract_version'=>'1.0.0','capabilities'=>[$def['capability']],'health'=>'healthy','health_checked_at'=>$now->format(DATE_ATOM)];}return $out;};
$record=static fn(string $id,string $owner):array=>['status'=>'accepted','evidence_id'=>$id,'owner'=>$owner,'artifact_ref'=>'evidence/'.strtolower($id),'recorded_at'=>'2026-08-04T09:20:00+00:00'];
$coreOps=static function()use($record):array{return[
'volume_trigger'=>$record('VOL-001','Support Operations')+['measurement_window'=>'90 days','metric'=>'safe_capacity_exceedance_days','threshold'=>20,'observed_value'=>27,'triggered'=>true],
'staffing'=>$record('STAFF-001','Support Operations')+['coverage_hours'=>'coverage.v1','queue_owners'=>['technical'=>'team-b'],'escalation_tree_approved'=>true,'emergency_diversion_approved'=>true,'privacy_training_complete'=>true,'quality_sampling_approved'=>true],
'privacy_review'=>$record('PRIV-001','Privacy Reviewer'),'security_review'=>$record('SEC-001','Security Reviewer'),'migration_plan'=>$record('MIG-001','Migration Owner'),'rollback_plan'=>$record('RB-001','Release Owner'),'zero_critical_high_defects'=>$record('DEF-001','QA Owner')+['critical_open'=>0,'high_open'=>0],];};
$approval=static function(string $environment='staging')use($identity):array{return['approved'=>true,'plan_version'=>'1.0','change_control_id'=>'CF02-ACT-1001','approved_by'=>'founder','approved_at'=>'2026-08-04T09:25:00+00:00','environment'=>$environment]+$identity;};
$external=static function()use($record,$identity):array{$out=[];foreach(['staging_acceptance','provider_acceptance','accessibility_review','load_resilience','backup_restore_rehearsal','rollback_rehearsal'] as $key){$out[$key]=$record(strtoupper(substr($key,0,4)).'-001','Acceptance Owner')+$identity+['passed'=>true];}return $out;};
$decision=static function(array $a,array $c,array $o,string $env='staging',bool $enabled=true)use($identity,$now){$provider=new class($a,$c,$o,$identity,$env,$enabled)implements ActivationEvidence{public function __construct(private array $a,private array $c,private array $o,private array $i,private string $e,private bool $s){}public function runtimeSwitchEnabled():bool{return $this->s;}public function runtimeEnvironment():string{return $this->e;}public function exactRuntimeIdentity():array{return $this->i;}public function founderApproval():array{return $this->a;}public function dependencyReadiness():array{return $this->c;}public function operationalEvidence():array{return $this->o;}};return(new ActivationGate($provider,$now))->evaluate();};

$test('activation denies empty evidence',static function()use($decision):void{assert(!$decision([],[],[], 'staging',false)->isAllowed());});
$test('staging activation accepts core exact-artifact evidence without circular production gates',static function()use($decision,$approval,$contracts,$coreOps):void{$r=$decision($approval(),$contracts(),$coreOps());assert($r->isAllowed(),implode(' | ',$r->reasons()));});
$test('production activation requires every external acceptance gate',static function()use($decision,$approval,$contracts,$coreOps):void{$r=$decision($approval('production'),$contracts(),$coreOps(),'production');assert(!$r->isAllowed());assert(in_array('Required operational evidence is missing or unaccepted: staging_acceptance.',$r->reasons(),true));});
$test('production activation accepts exact external evidence',static function()use($decision,$approval,$contracts,$coreOps,$external):void{$r=$decision($approval('production'),$contracts(),array_merge($coreOps(),$external()),'production');assert($r->isAllowed(),implode(' | ',$r->reasons()));});
$test('activation rejects artifact mismatch and wrong hash format',static function()use($decision,$approval,$contracts,$coreOps):void{$a=$approval();$a['source_sha']=str_repeat('c',40);$a['package_sha256']='bad';$reasons=$decision($a,$contracts(),$coreOps())->reasons();assert(in_array('Founder approval does not target the exact artifact: source_sha.',$reasons,true));assert(in_array('Founder approval does not target the exact artifact: package_sha256.',$reasons,true));});
$test('dependency health is deterministic stale future and owner aware',static function()use($decision,$approval,$contracts,$coreOps):void{$c=$contracts();$first=array_key_first($c);$c[$first]['health_checked_at']='2026-08-04T08:00:00+00:00';$c[$first]['owner']='Other';$reasons=$decision($approval(),$c,$coreOps())->reasons();assert(in_array('Dependency contract health evidence is stale: '.$first.'.',$reasons,true));assert(in_array('Dependency contract owner mismatch: '.$first.'.',$reasons,true));});
$test('optional dependency stays optional until explicitly enabled',static function()use($decision,$approval,$contracts,$coreOps):void{$c=$contracts();$r=$decision($approval(),$c,$coreOps());assert($r->isAllowed());$c['cf_03_financial_contract']=['enabled'=>true,'ready'=>false];assert(!$decision($approval(),$c,$coreOps())->isAllowed());});
$test('activation requires canonical Founder and valid timestamps',static function()use($decision,$approval,$contracts,$coreOps):void{$a=$approval();$a['approved_by']='administrator';$a['approved_at']='not-a-date';$reasons=$decision($a,$contracts(),$coreOps())->reasons();assert(in_array('Activation approval is not bound to the canonical Founder identity.',$reasons,true));assert(in_array('Founder approval timestamp is not valid ISO 8601.',$reasons,true));});
$test('activation rejects boolean operational claims and incomplete staffing',static function()use($decision,$approval,$contracts,$coreOps):void{$o=$coreOps();$o['privacy_review']=true;$o['staffing']['queue_owners']=['technical'=>''];$reasons=$decision($approval(),$contracts(),$o)->reasons();assert(in_array('Required operational evidence is missing or unaccepted: privacy_review.',$reasons,true));assert(in_array('Staffing queue-owner evidence contains an invalid assignment.',$reasons,true));});
$test('taxonomy and purpose-separated queues validate',static function():void{$t=SupportTaxonomy::defaults();assert(count($t)===13);assert(SupportTaxonomy::validate()===[]);assert(QueueRegistry::validate()===[]);assert(count(QueueRegistry::defaults())===12);});
$test('configuration constructors reject malformed lists',static function():void{$a=false;try{new SupportCategory('bad','Bad','technical',[['bad']],'C2','Owner',['issue_type']);}catch(InvalidArgumentException){$a=true;}assert($a);$b=false;try{new QueueDefinition('bad','Bad',['technical','technical'],['technical_support'],'team_lead','coverage.bad.v1','specialist_agent');}catch(InvalidArgumentException){$b=true;}assert($b);});
$test('high-risk staffing separation rejects conflicts',static function():void{$bad=StaffingRegistry::validateHighRiskSeparation(['requester'=>'u','original_decider'=>'a','reviewer'=>'a','executor'=>'a','reconciler'=>'b','auditor'=>'a']);assert(count($bad)>=3);});
$test('change-control validates rollback evidence',static function():void{$r=['id'=>'CF02-CCR-0001','requested_by'=>'Founder','recorded_at'=>'2026-08-03T17:41:00+05:00','affected_files'=>['CF-02','File 24'],'requirement_ids'=>['CF02-FR-030'],'old_rule'=>'No baseline','new_rule'=>'Dormant foundation','rationale'=>'Controlled implementation','data_impact'=>'No runtime data','security_privacy_impact'=>'Fail closed','shariah_impact'=>'No change','migration_plan'=>'None','rollback_plan'=>'Revert branch','test_plan'=>'Automated tests','approval_status'=>'implementation_authorized'];assert(ChangeControlRecord::validate($r)===[]);unset($r['rollback_plan']);assert(ChangeControlRecord::validate($r)!==[]);});
$test('support case state law rejects illegal closure',static function():void{$m=new CaseStateMachine();assert($m->canTransition(CaseState::New,CaseState::Triaged));$x=false;try{$m->assertTransition(CaseState::New,CaseState::Closed);}catch(InvalidTransition){$x=true;}assert($x);});
if($failures!==[])exit(1);fwrite(STDOUT,"All CF-02 C2-A activation and governance tests passed.\n");
