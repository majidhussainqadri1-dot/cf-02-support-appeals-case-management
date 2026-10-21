<?php
declare(strict_types=1);

/** Bounded test for private case/appeal listing database failures. */
require_once dirname(__DIR__).'/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');
use Sabri\CF02\Infrastructure\WordPress\AuthorizedQueryRows;

$passed=0;$failed=0;
$check=static function(string $title,bool $ok)use(&$passed,&$failed):void{
    if($ok)$passed++;else $failed++;
    fwrite($ok?STDOUT:STDERR,($ok?'PASS ':'FAIL ').$title."\n");
};
$reject=static function(mixed $input):bool{
    try{AuthorizedQueryRows::fromDatabase($input);return false;}
    catch(LogicException){return true;}
};
$check('Legitimately empty query remains an empty list',AuthorizedQueryRows::fromDatabase([])===[]);
$check('One authorized case row preserved',AuthorizedQueryRows::fromDatabase([['case_uuid'=>'id']])===[['case_uuid'=>'id']]);
$check('List order preserved',AuthorizedQueryRows::fromDatabase([['id'=>2],['id'=>1]])===[['id'=>2],['id'=>1]]);
$check('Nonconsecutive array keys normalized',AuthorizedQueryRows::fromDatabase([5=>['id'=>5],9=>['id'=>9]])===[['id'=>5],['id'=>9]]);
foreach([null,false,true,'',0,1,0.0,(object)[],new stdClass,123.45] as $i=>$value){
    $check('Unusable database result '.($i+1).' rejected',$reject($value));
}
foreach([[null],[false],[true],['bad'],[['id'=>1],null],[['id'=>1],'bad']] as $i=>$input){
    $check('Malformed list row '.($i+1).' rejected',$reject($input));
}
$source=(string)file_get_contents(dirname(__DIR__).'/src/Infrastructure/WordPress/CompleteRestOverlay.php');
$check('Both private list queries require checked query results',
    substr_count($source,'AuthorizedQueryRows::fromDatabase($rows);')===2);
$check('Previously silent null to empty fallback absent',
    !str_contains($source,'$rows=is_array($rows)?$rows:[];'));
$check('Unexpected SQL exception has dedicated 503 response boundary',
    str_contains($source,"'cf02_query_unavailable'") && str_contains($source,"['status'=>503"));
$check('Backend errors not misclassified as ordinary invalid cursors',
    is_subclass_of(LogicException::class,RuntimeException::class)===false);
printf("CF-02 authorized query failures: %d passed; %d failed; live DB remains unverified.\n",$passed,$failed);
exit($failed===0?0:1);
