<?php

declare(strict_types=1);
ini_set('assert.exception','1');assert_options(ASSERT_ACTIVE,1);assert_options(ASSERT_EXCEPTION,1);
require_once dirname(__DIR__).'/src/Autoload.php';\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');

use Sabri\CF02\Infrastructure\WordPress\Schema;
use Sabri\CF02\Infrastructure\WordPress\SchemaExtension;
use Sabri\CF02\Infrastructure\WordPress\SchemaCompletion;
use Sabri\CF02\Operations\TrainingRegister;
use Sabri\CF02\Release\ReleaseGate;
use Sabri\CF02\Resilience\LoadBudget;
use Sabri\CF02\Resilience\RecoveryEvidence;
use Sabri\CF02\Security\DataCipher;

$failures=[];$test=static function(string $n,callable $c)use(&$failures):void{try{$c();fwrite(STDOUT,"PASS {$n}\n");}catch(Throwable $e){$failures[]=$n.': '.$e->getMessage();fwrite(STDERR,"FAIL {$n}: {$e->getMessage()}\n");}};

$test('canonical schema covers all support case appeal evidence quality migration and audit truth',static function():void{
    $statements=array_merge(
        Schema::statements('wp_','DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'),
        SchemaExtension::statements('wp_','DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'),
        SchemaCompletion::statements('wp_','DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')
    );
    foreach(['cases','messages','attachments','assignments','sla_timers','appeals','commands','outbox','holds','configuration','migration','audit','intake_replay','tasks','appeal_dossiers','quality_reviews','feedback','retention_ledger','command_payloads','outbox_payloads','events','representatives','case_links','inbound_receipts','attachment_tokens','merge_redirects','incident_links','metrics','note_revisions','key_rotation','repair_ledger'] as $table){
        assert(isset($statements[$table]));
        assert(str_contains($statements[$table],'CREATE TABLE wp_cf02_'));
    }
    assert(SchemaCompletion::VERSION==='1.3.0');
});

$test('data cipher provides authenticated encryption at rest',static function():void{
    $cipher=new DataCipher(str_repeat('key-material-',8));$encrypted=$cipher->encrypt('private case message');
    assert($encrypted!=='private case message');assert($cipher->decrypt($encrypted)==='private case message');
});

$test('load budget detects saturation and preserves safe degradation action',static function():void{
    $budget=new LoadBudget(1000,500,1000,0.02,60);
    assert($budget->evaluate(900,400,500,0.01,20)['passed']);
    $failed=$budget->evaluate(1100,800,1500,0.05,100);assert(!$failed['passed']);assert($failed['action']==='degrade_noncritical_work_and_escalate');
});

$test('recovery evidence requires restore authorization deletion and downstream reconciliation',static function():void{
    $evidence=RecoveryEvidence::create(new DateTimeImmutable('2026-08-04T08:00:00+05:00'),300,1800,['database','object_storage','configuration','queues','audit','provider_mapping'],true,true,true,true,'artifact');
    assert($evidence->passed());assert(strlen($evidence->artifactHash())===64);
});

$test('training readiness covers privacy security fairness emergency accessibility and SLA',static function():void{
    $at=new DateTimeImmutable('2026-08-04T08:10:00+05:00');$register=new TrainingRegister();
    $register->certify('staff:1',['privacy','security','appeals_fairness','emergency_diversion','accessibility','sla_operations'],$at->modify('+1 year'),'trainer:1',$at);
    assert($register->readiness(['staff:1'],$at)['ready']);
});

$test('release gate remains blocked until staging package parity and Founder approval exist',static function():void{
    $evidence=array_fill_keys(['all_requirements_coded','two_review_rounds_per_part','full_ci_green','dependency_contracts_green','security_privacy_review_passed','accessibility_matrix_passed','load_resilience_passed','migration_reconciled','backup_restore_passed','rollback_rehearsed','staffing_training_ready','staging_accepted','package_parity_verified','founder_exact_version_approved','zero_known_critical_high_defects'],true);
    $evidence['staging_accepted']=false;
    $decision=(new ReleaseGate())->evaluate($evidence,new DateTimeImmutable());assert(!$decision['allowed']);assert(in_array('staging_accepted',$decision['missing'],true));
});

if($failures!==[]){exit(1);}fwrite(STDOUT,"All CF-02 C2-H tests passed.\n");
