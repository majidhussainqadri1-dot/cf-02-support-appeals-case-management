<?php

declare(strict_types=1);
ini_set('assert.exception','1');assert_options(ASSERT_ACTIVE,1);assert_options(ASSERT_EXCEPTION,1);
require_once dirname(__DIR__).'/src/Autoload.php';\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');

use Sabri\CF02\Governance\InstitutionalDueProcessPolicy;
use Sabri\CF02\Governance\ServiceEqualityPolicy;
use Sabri\CF02\Integration\PrivacySafeOutcomeProjection;

$failures=[];$test=static function(string $name,callable $case)use(&$failures):void{try{$case();fwrite(STDOUT,"PASS {$name}\n");}catch(Throwable $error){$failures[]=$name.': '.$error->getMessage();fwrite(STDERR,"FAIL {$name}: {$error->getMessage()}\n");}};
$root=dirname(__DIR__);

$test('every privilege signal is rejected even when false or zero',static function():void{
    foreach(ServiceEqualityPolicy::prohibitedPrivilegeFields() as $field){
        foreach([false,0,'none'] as $value){
            $blocked=false;
            try{ServiceEqualityPolicy::assertNoPrivilegeSignals([$field=>$value]);}catch(InvalidArgumentException){$blocked=true;}
            assert($blocked);
        }
    }
});

$test('pending and unimplemented corrective appeals cannot create adverse ranking data',static function():void{
    $records=array_fill(0,10,['appeal_state'=>'closed','outcome'=>'uphold','implementation_confirmed'=>true]);
    $records[]=['appeal_state'=>'under_review','outcome'=>'uphold','implementation_confirmed'=>false];
    $records[]=['appeal_state'=>'decided','outcome'=>'overturn','implementation_confirmed'=>false];
    $result=(new PrivacySafeOutcomeProjection())->project($records,'p2',10);
    assert($result['cohort_size']===10);
    assert($result['excluded_pending']===1);
    assert($result['excluded_unimplemented']===1);
});

$test('institutional due process rejects donor influence and self-incomplete records',static function():void{
    $errors=InstitutionalDueProcessPolicy::validate([
        'classification'=>'alleged_serious_violation','proposed_action'=>'warning','donor_status'=>'donor',
    ]);
    assert(count($errors)>=8);
    assert((bool)array_filter($errors,static fn(string $error):bool=>str_contains($error,'privilege signal')));
});

$test('frontend has no inline runtime form templates and supports logical responsive layout',static function()use($root):void{
    $surface=file_get_contents($root.'/src/Infrastructure/WordPress/CompleteFrontendSurfaces.php');
    $legacy=file_get_contents($root.'/src/Infrastructure/WordPress/CompleteFrontendScript.php');
    $css=file_get_contents($root.'/assets/css/cf02-frontend.css');
    $js=file_get_contents($root.'/assets/js/cf02-frontend.js');
    assert(!str_contains($surface,'wp_add_inline_style'));
    assert(!str_contains($surface,'<script>'));
    assert(str_contains($legacy,"return '';"));
    assert(str_contains($css,'@media (max-width: 48rem)'));
    assert(str_contains($css,'@media (max-width: 30rem)'));
    assert(str_contains($css,'@media (forced-colors: active)'));
    assert(str_contains($js,'focusPanel'));
    assert(str_contains($js,'navigator.onLine'));
    assert(!str_contains($js,"innerHTML='<label"));
});

if($failures!==[])exit(1);fwrite(STDOUT,"CF-02 fresh adversarial three-plan review round 2 passed.\n");
