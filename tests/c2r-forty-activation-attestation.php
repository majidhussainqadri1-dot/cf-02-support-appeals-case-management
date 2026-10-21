<?php

declare(strict_types=1);

/** Forty independent bounded activation/owner health cases (C2-R).
 * Real provider attestations, deployed parity, WordPress and staging are not
 * established by these synthetic inputs or contract validators.
 */
if (!defined('SMC_CONTRACT_VERSION')) define('SMC_CONTRACT_VERSION','99.0.0');
function smc_membership_assertions(): array { return ['present' => true]; }
$GLOBALS['cf02_manifest_filter']=null;
function apply_filters(string $name,mixed $defaults,mixed ...$args): mixed {
    $callback=$GLOBALS['cf02_manifest_filter'];
    return $name==='cf02_dependency_contract_evidence' && is_callable($callback)
        ? $callback($defaults) : $defaults;
}
function get_option(string $name,mixed $fallback=[]):mixed { return $fallback; }
require_once dirname(__DIR__).'/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');

use Sabri\CF02\Activation\WordPressActivationEvidence;
use Sabri\CF02\Contracts\RequiredCompanionContracts as Registry;
$now=new DateTimeImmutable('2026-10-21T10:18:00+05:00');
$definitions=Registry::definitions();
$evidence=new WordPressActivationEvidence();
$defaults=$evidence->dependencyReadiness();
$passed=0;$failed=[];$checks=0;
$check=static function(string $label,bool $condition)use(&$passed,&$failed,&$checks):void{
    ++$checks;if($condition)$passed++;else $failed[]=$label;
    fwrite($condition?STDOUT:STDERR,sprintf("%s %02d/40 %s\n",$condition?'PASS':'FAIL',$checks,$label));
};
$issue=static function(array $all,string $name)use($now):bool{
    return array_reduce(Registry::validate($all,$now),
        static fn(bool $yes,string $message):bool=>$yes||str_contains($message,': '.$name.'.'),false);
};
$allReady=static function()use($definitions,$now):array{
    $all=[];
    foreach($definitions as $key=>$d){
        $all[$key]=[
            'enabled'=>$d['required'],
            'ready'=>true,'owner'=>$d['owner'],
            'contract_version'=>'1.3.0',
            'capabilities'=>[$d['capability']],
            'health'=>'healthy',
            'health_checked_at'=>$now->format(DATE_ATOM)
        ];
    }
    return $all;
};
$baseline=$allReady();
foreach($definitions as $key=>$def){ // 1..17
    $check("Default manifest for $key is non-attesting and non-escalating",
        isset($defaults[$key]) &&
        ($defaults[$key]['ready']??null)===false &&
        ($defaults[$key]['health']??null)==='unknown' &&
        ($defaults[$key]['health_checked_at']??false)===null &&
        ($defaults[$key]['enabled']??null)===$def['required']
    );
}
$file00='file_00_membership_contract';
$check('Installed File 00 function must not become native owner readiness',($defaults[$file00]['ready']??null)===false); //18
$check('Constant presence must not forge File 00 owner version',($defaults[$file00]['contract_version']??'missing')===null); //19
$check('No synthetic File 00 healthy assertion',($defaults[$file00]['health']??null)==='unknown'); //20
$check('No fabricated fresh health timestamp',($defaults[$file00]['health_checked_at']??'invalid')===null); //21
$check('All mandatory defaults are refused by validation',
    count(array_filter($definitions,static fn(array $x):bool=>$x['required']))===14 &&
    count(Registry::validate($defaults,$now))>=14); //22
$check('Optional contracts disabled by default',
    count(array_filter($definitions,static fn(array $x):bool=>!$x['required']))===3 &&
    count(array_filter($defaults,static fn(array $x):bool=>($x['enabled']??false)===false))===3); //23
$check('Approved owner manifests can be valid',Registry::validate($baseline,$now)===[]); //24
$check('A missing native File 00 owner contract blocks activation',
    $issue(array_diff_key($baseline,[$file00=>true]),$file00)); //25
$forged=$baseline;$forged[$file00]['owner']='CF-02';
$check('Forged File 00 owner rejected',$issue($forged,$file00)); //26
$forged=$baseline;$forged[$file00]['ready']=false;
$check('Unready File 00 denied',$issue($forged,$file00)); //27
$forged=$baseline;$forged[$file00]['capabilities']=['other'];
$check('Missing identity assertion capability denied',$issue($forged,$file00)); //28
$forged=$baseline;$forged[$file00]['health']='unknown';
$check('Unknown File 00 health denied',$issue($forged,$file00)); //29
$forged=$baseline;$forged[$file00]['contract_version']=null;
$check('Missing File 00 contract version denied',$issue($forged,$file00)); //30
$forged=$baseline;$forged[$file00]['contract_version']='not-a-version';
$check('Malformed File 00 contract version denied',$issue($forged,$file00)); //31
$forged=$baseline;$forged[$file00]['health_checked_at']='';
$check('Missing File 00 time denied',$issue($forged,$file00)); //32
$forged=$baseline;$forged[$file00]['health_checked_at']=$now->modify('-16 minutes')->format(DATE_ATOM);
$check('Stale File 00 owner health denied',$issue($forged,$file00)); //33
$forged=$baseline;$forged[$file00]['health_checked_at']=$now->modify('+2 minutes')->format(DATE_ATOM);
$check('Future-dated File 00 health denied',$issue($forged,$file00)); //34
$forged=$baseline;$forged[$file00]['health_checked_at']=$now->modify('-15 minutes')->format(DATE_ATOM);
$check('15 minute inclusive freshness accepted',Registry::validate($forged,$now)===[]); //35
$forged=$baseline;$forged['clinical_safety_owner_contract']['enabled']=true;$forged['clinical_safety_owner_contract']['ready']=false;
$check('Enabled conditional clinical owner fails closed',$issue($forged,'clinical_safety_owner_contract')); //36
$forged=$baseline;$forged['cf_03_financial_contract']['enabled']=true;$forged['cf_03_financial_contract']['ready']=false;
$check('Enabled conditional finance owner fails closed',$issue($forged,'cf_03_financial_contract')); //37
$forged=$baseline;$forged['file_26_ranking_contract']['enabled']=true;$forged['file_26_ranking_contract']['ready']=false;
$check('Enabled conditional ranking owner fails closed',$issue($forged,'file_26_ranking_contract')); //38
$GLOBALS['cf02_manifest_filter']=static fn(array $defaults):array=>array_merge($defaults,[$file00=>$baseline[$file00]]);
$withProvider=$evidence->dependencyReadiness();
$check('Healthy File 00 evidence is only provider supplied, not default',
    ($withProvider[$file00]['ready']??false)===true &&
    ($withProvider['file_18_marketplace_case_contract']['ready']??true)===false); //39
$GLOBALS['cf02_manifest_filter']=static fn(array $defaults):string=>'malformed';
$invalid=$evidence->dependencyReadiness();
$check('Malformed provider filter does not lift native owner readiness',
    ($invalid[$file00]['ready']??true)===false); //40
printf("CF-02 C2-R activation attestation: %d/%d passed; %d failed; external verification not claimed.\n",$passed,$checks,count($failed));
exit($checks===40 && $failed===[] ? 0 : 1);
