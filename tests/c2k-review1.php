<?php

declare(strict_types=1);
ini_set('assert.exception','1');assert_options(ASSERT_ACTIVE,1);assert_options(ASSERT_EXCEPTION,1);
require_once dirname(__DIR__).'/src/Autoload.php';\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');

use Sabri\CF02\Configuration\CategoryRoutingPolicy;
use Sabri\CF02\Governance\InstitutionalDueProcessPolicy;
use Sabri\CF02\Integration\PrivacySafeOutcomeProjection;

$failures=[];$test=static function(string $name,callable $case)use(&$failures):void{try{$case();fwrite(STDOUT,"PASS {$name}\n");}catch(Throwable $error){$failures[]=$name.': '.$error->getMessage();fwrite(STDERR,"FAIL {$name}: {$error->getMessage()}\n");}};
$root=dirname(__DIR__);

$test('legacy learning category never routes to financial coordination',static function():void{
    assert(CategoryRoutingPolicy::queueFor('learning_billing')==='learning');
    assert(CategoryRoutingPolicy::queueFor('learning_access')==='learning');
});

$test('provider adapter cannot choose a noncanonical queue',static function()use($root):void{
    $source=file_get_contents($root.'/src/Infrastructure/WordPress/IntakeRepository.php');
    assert(str_contains($source,"CategoryRoutingPolicy::queueFor(\$category)"));
    assert(str_contains($source,"\$payload['queue'] = CategoryRoutingPolicy::queueFor(\$category)"));
});

$test('good-faith inquiry is protected from discipline',static function():void{
    $errors=InstitutionalDueProcessPolicy::validate(['classification'=>'good_faith_inquiry','proposed_action'=>'role_removal']);
    assert(in_array('Good-faith inquiry or disputed interpretation cannot trigger disciplinary action.',$errors,true));
});

$test('closed disciplinary action requires native implementation evidence',static function():void{
    $record=[
        'classification'=>'alleged_serious_violation','proposed_action'=>'access_revocation','status'=>'closed',
        'notice_reference'=>'N1','policy_version'=>'P1','reviewer_reference'=>'R1','appeal_route'=>'/appeal',
        'native_owner_reference'=>'File 00','allegations'=>['A'],'evidence_references'=>['E'],
        'response_opportunity_provided'=>true,'conflict_check_passed'=>true,'proportionality_assessed'=>true,
        'appeal_available'=>true,'implementation_verification_required'=>true,
    ];
    assert(in_array('Closed disciplinary matter requires an implementation reference.',InstitutionalDueProcessPolicy::validate($record),true));
});

$test('privacy projection rejects identifiers and narrative data',static function():void{
    $projection=new PrivacySafeOutcomeProjection();
    foreach(['case_id','user_id','message','attachment','donation_amount'] as $field){
        $blocked=false;
        try{$projection->project([['appeal_state'=>'closed','outcome'=>'uphold','implementation_confirmed'=>true,$field=>'secret']],'p',10);}catch(InvalidArgumentException){$blocked=true;}
        assert($blocked);
    }
});

if($failures!==[])exit(1);fwrite(STDOUT,"CF-02 three-plan review round 1 passed.\n");
