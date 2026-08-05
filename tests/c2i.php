<?php

declare(strict_types=1);
ini_set('assert.exception','1');assert_options(ASSERT_ACTIVE,1);assert_options(ASSERT_EXCEPTION,1);
require_once dirname(__DIR__).'/src/Autoload.php';\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');

use Sabri\CF02\Application\RuntimeWorkflowPolicy;
use Sabri\CF02\Authorization\PrincipalContext;
use Sabri\CF02\Contracts\SupportContractCatalog;
use Sabri\CF02\Infrastructure\WordPress\RouteRegistrar;
use Sabri\CF02\Infrastructure\WordPress\SchemaExtension;
use Sabri\CF02\Infrastructure\WordPress\SchemaCompletion;

$failures=[];$test=static function(string $n,callable $c)use(&$failures):void{try{$c();fwrite(STDOUT,"PASS {$n}\n");}catch(Throwable $e){$failures[]=$n.': '.$e->getMessage();fwrite(STDERR,"FAIL {$n}: {$e->getMessage()}\n");}};

$test('plan command query event and category catalog is complete and immutable',static function():void{
    assert(count(SupportContractCatalog::commands())===33);
    assert(count(SupportContractCatalog::queries())===20);
    assert(count(SupportContractCatalog::events())>=24);
    assert(count(SupportContractCatalog::categories())===13);
    foreach(['CreateCase','MergeCases','SubmitAppeal','RequestNativeDecisionAction','RollBackSupportConfiguration'] as $name){SupportContractCatalog::assertCommand($name);}
    foreach(['GetMyCases','SearchAuthorizedCases','GetAppealDossier','GetPurgeReconciliation'] as $name){SupportContractCatalog::assertQuery($name);}
    foreach(['account_access','verification','learning_access','publishing','clinic_appointment','messages_calls','media_pdf','marketplace','privacy_data_rights','safety_abuse','accessibility','technical','institutional_governance'] as $category){SupportContractCatalog::assertCategory($category);}
});

$test('runtime state laws preserve explicit case appeal and attachment lifecycles',static function():void{
    RuntimeWorkflowPolicy::assertCase('new','triaged');
    RuntimeWorkflowPolicy::assertCase('resolved','closed');
    RuntimeWorkflowPolicy::assertCase('closed','reopened');
    RuntimeWorkflowPolicy::assertAppeal('submitted','eligibility_review');
    RuntimeWorkflowPolicy::assertAppeal('decided','implemented');
    RuntimeWorkflowPolicy::assertAttachment('uploaded','quarantined');
    RuntimeWorkflowPolicy::assertAttachment('scanned','available');
    $blocked=false;try{RuntimeWorkflowPolicy::assertCase('new','closed');}catch(DomainException){$blocked=true;}assert($blocked);
});

$test('File 00 authorization assertion is versioned expiring suspended-aware and recent-auth bound',static function():void{
    $at=new DateTimeImmutable('2026-08-04T00:00:00+00:00');
    $context=new PrincipalContext('user:7',7,['support_agent'],['case.assigned.read'],['user:8'],false,$at,'File 00','1.2.3',$at,$at->modify('+5 minutes'));
    assert($context->validAt($at->modify('+1 minute')));
    assert($context->represents('user:8'));
    assert($context->recentlyAuthenticated($at->modify('+10 minutes')));
    assert(!$context->validAt($at->modify('+6 minutes')));
    $rejected=false;try{new PrincipalContext('user:7',7,[],[],[],false,$at,'File 24','1.0.0',$at,$at->modify('+5 minutes'));}catch(InvalidArgumentException){$rejected=true;}assert($rejected);
});

$test('canonical routes preserve CF-02 ownership without creating a second shell',static function():void{
    $routes=RouteRegistrar::contracts();
    foreach(['/support','/support/cases/{id}','/support/appeals/{id}','/admin/support','/admin/support/cases/{id}','/admin/support/quality','/api/support/v1/*'] as $route){assert(isset($routes[$route]));assert($routes[$route]['owner']==='CF-02');}
    assert($routes['/support/cases/{id}']['cache']==='no-store');
});

$test('schema 1.3 covers runtime contracts replay secure payloads links metrics and lifecycle evidence',static function():void{
    assert(SchemaCompletion::VERSION==='1.3.0');
    $sql=array_merge(SchemaExtension::statements('wp_','DEFAULT CHARACTER SET utf8mb4'),SchemaCompletion::statements('wp_','DEFAULT CHARACTER SET utf8mb4'));
    foreach(['intake_replay','tasks','appeal_dossiers','quality_reviews','feedback','command_payloads','outbox_payloads','events','representatives','case_links','inbound_receipts','attachment_tokens','merge_redirects','incident_links','metrics','note_revisions','retention_ledger','key_rotation','repair_ledger'] as $table){assert(isset($sql[$table]),$table);assert(str_contains($sql[$table],'CREATE TABLE wp_cf02_'));}
});

if($failures!==[]){exit(1);}fwrite(STDOUT,"All CF-02 C2-I complete runtime integration tests passed.\n");
