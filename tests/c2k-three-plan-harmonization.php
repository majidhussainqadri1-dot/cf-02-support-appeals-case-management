<?php

declare(strict_types=1);
ini_set('assert.exception','1');assert_options(ASSERT_ACTIVE,1);assert_options(ASSERT_EXCEPTION,1);
require_once dirname(__DIR__).'/src/Autoload.php';\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');

use Sabri\CF02\Configuration\CategoryRoutingPolicy;
use Sabri\CF02\Configuration\QueueRegistry;
use Sabri\CF02\Configuration\SupportTaxonomy;
use Sabri\CF02\Contracts\RequiredCompanionContracts;
use Sabri\CF02\Contracts\SupportContractCatalog;
use Sabri\CF02\Governance\InstitutionalDueProcessPolicy;
use Sabri\CF02\Governance\ServiceEqualityPolicy;
use Sabri\CF02\Integration\PrivacySafeOutcomeProjection;

$failures=[];$test=static function(string $name,callable $case)use(&$failures):void{try{$case();fwrite(STDOUT,"PASS {$name}\n");}catch(Throwable $error){$failures[]=$name.': '.$error->getMessage();fwrite(STDERR,"FAIL {$name}: {$error->getMessage()}\n");}};
$root=dirname(__DIR__);

$test('single-free-tier taxonomy replaces learning billing and preserves migration alias',static function():void{
    $taxonomy=SupportTaxonomy::defaults();
    assert(count($taxonomy)===13);
    assert(isset($taxonomy['learning_access']));
    assert(!isset($taxonomy['learning_billing']));
    assert($taxonomy['learning_access']->label()==='Learning Access');
    assert($taxonomy['learning_access']->requiredSkills()===['learning_support']);
    assert(SupportContractCatalog::normalizeCategory('learning_billing')==='learning_access');
    SupportContractCatalog::assertCategory('learning_billing');
});

$test('every category resolves to its canonical configured queue',static function():void{
    assert(SupportTaxonomy::validate()===[]);
    assert(QueueRegistry::validate()===[]);
    assert(count(QueueRegistry::defaults())===12);
    foreach(SupportTaxonomy::defaults() as $key=>$category){
        assert(CategoryRoutingPolicy::queueFor($key)===$category->queueKey());
        assert(isset(QueueRegistry::defaults()[$category->queueKey()]));
    }
    assert(CategoryRoutingPolicy::queueFor('learning_billing')==='learning');
});

$test('donation and sponsorship signals cannot influence priority or appeals',static function():void{
    assert(ServiceEqualityPolicy::requesterPriority('account_blocked','time_sensitive')==='P2');
    assert(ServiceEqualityPolicy::requesterPriority('single_action','normal')==='P3');
    foreach(ServiceEqualityPolicy::prohibitedPrivilegeFields() as $field){
        $blocked=false;
        try{ServiceEqualityPolicy::assertNoPrivilegeSignals([$field=>true]);}catch(InvalidArgumentException){$blocked=true;}
        assert($blocked);
    }
});

$test('Islamic institutional governance requires notice evidence response independence proportionality and appeal',static function():void{
    $record=[
        'classification'=>'alleged_serious_violation','proposed_action'=>'warning','status'=>'pending',
        'notice_reference'=>'NOTICE-1','policy_version'=>'CHAT-GOV-023-v2.1','reviewer_reference'=>'reviewer:independent',
        'appeal_route'=>'/support/appeals/{id}','native_owner_reference'=>'institutional-owner:v1',
        'allegations'=>['Documented alleged violation'],'evidence_references'=>['EVID-1'],
        'response_opportunity_provided'=>true,'conflict_check_passed'=>true,'proportionality_assessed'=>true,
        'appeal_available'=>true,'implementation_verification_required'=>true,
    ];
    assert(InstitutionalDueProcessPolicy::validate($record)===[]);
    $record['conflict_check_passed']=false;
    assert(InstitutionalDueProcessPolicy::validate($record)!==[]);
    assert(InstitutionalDueProcessPolicy::validate(['classification'=>'good_faith_inquiry','proposed_action'=>'none'])===[]);
});

$test('File 26 receives only privacy-thresholded final implemented aggregate outcomes',static function():void{
    $projection=new PrivacySafeOutcomeProjection();
    $small=$projection->project(array_fill(0,19,['appeal_state'=>'closed','outcome'=>'uphold','implementation_confirmed'=>true]),'rank-policy-1',20);
    assert($small['eligible_for_ranking_consumption']===false);
    assert($small['outcomes']===null);
    $records=array_fill(0,20,['appeal_state'=>'closed','outcome'=>'uphold','implementation_confirmed'=>true]);
    $records[]=['appeal_state'=>'under_review','outcome'=>'uphold','implementation_confirmed'=>false];
    $result=$projection->project($records,'rank-policy-1',20);
    assert($result['eligible_for_ranking_consumption']===true);
    assert($result['cohort_size']===20);
    assert($result['excluded_pending']===1);
    assert($result['appeal_use_penalty']===false);
    assert($result['donation_or_payment_signal']===false);
});

$test('File 26 contract is conditional and fail-closed when enabled',static function():void{
    $definitions=RequiredCompanionContracts::definitions();
    assert(isset($definitions['file_26_ranking_contract']));
    assert($definitions['file_26_ranking_contract']['required']===false);
    $now=new DateTimeImmutable('2026-08-05T14:30:00+00:00');
    assert(RequiredCompanionContracts::validate([], $now)!==[]); // hard dependencies remain required.
    $optional=['file_26_ranking_contract'=>['enabled'=>true,'ready'=>false]];
    $errors=RequiredCompanionContracts::validate($optional,$now);
    assert(in_array('Required dependency contract is not ready: file_26_ranking_contract.',$errors,true));
});

$test('frontend is externalized localized green icon-led and resilient',static function()use($root):void{
    $surface=file_get_contents($root.'/src/Infrastructure/WordPress/CompleteFrontendSurfaces.php');
    $css=file_get_contents($root.'/assets/css/cf02-frontend.css');
    $js=file_get_contents($root.'/assets/js/cf02-frontend.js');
    assert(str_contains($surface,"assets/css/cf02-frontend.css"));
    assert(str_contains($surface,"assets/js/cf02-frontend.js"));
    assert(str_contains($surface,'data-i18n'));
    assert(!str_contains($surface,'CompleteFrontendScript::render'));
    assert(str_contains($css,'--sabri-color-primary'));
    assert(str_contains($css,'#198754'));
    assert(str_contains($css,'min-block-size: 44px'));
    assert(str_contains($css,'border-inline-start'));
    assert(str_contains($js,"window.addEventListener('offline'"));
    assert(str_contains($js,"aria-busy"));
    assert(str_contains($js,'cf02-icon'));
    assert(is_file($root.'/languages/cf-02-support-appeals-case-management-ur.mo'));
    assert(filesize($root.'/languages/cf-02-support-appeals-case-management-ur.mo')>1000);
});

if($failures!==[])exit(1);fwrite(STDOUT,"All CF-02 three-plan harmonization tests passed.\n");
