<?php
declare(strict_types=1);

/**
 * C2-S: real failure-path regressions for private list queries and destructive
 * retention purge (CF02-FR-026/031/034; CV-265/266/272/278/283).
 * These are PHP fake-DB tests; production/staging DB parity is separate.
 */
if (!defined('ARRAY_A')) define('ARRAY_A','ARRAY_A');
require_once dirname(__DIR__).'/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');

use Sabri\CF02\Infrastructure\WordPress\OperationsRepository;

final class C2SFakeDb {
    public string $prefix='wp_';
    public mixed $rows=[];
    public mixed $holdCount='0';
    public mixed $deleteResult=1;
    public mixed $queryResult=0;
    public int $deleteCalls=0;
    public array $queries=[];
    public function get_results(string $sql,string $format):mixed {return $this->rows;}
    public function get_var(string $sql):mixed {return $this->holdCount;}
    public function get_row(string $sql,string $format):mixed {
        return str_contains($sql,'wp_cf02_cases')
            ? ['case_uuid'=>'CF02-CASE-TEST','category'=>'technical']
            : null;
    }
    public function prepare(string $sql,mixed ...$args):string {return $sql;}
    public function delete(string $table,array $where):mixed {
        $this->deleteCalls++;return $this->deleteResult;
    }
    public function query(string $sql):mixed {
        $this->queries[]=$sql;
        return $this->queryResult;
    }
}
$pass=0;$fail=[];
$check=static function(string $name,bool $ok)use(&$pass,&$fail):void{
    $ok?$pass++:$fail[]=$name;
    fwrite($ok?STDOUT:STDERR,($ok?'PASS ':'FAIL ').$name."\n");
};
$throws=static function(callable $fn):bool {
    try{$fn();return false;}catch(RuntimeException|LogicException){return true;}
};
$db=new C2SFakeDb();$GLOBALS['wpdb']=$db;$repo=new OperationsRepository();
$ref=new ReflectionClass($repo);
$rows=$ref->getMethod('rows');
$del=$ref->getMethod('purgeDelete');
$query=$ref->getMethod('purgeQuery');
foreach ([null,false,true,'',0,new stdClass] as $i=>$bad) {
    $db->rows=$bad;
    $check('Invalid multi-row SQL result is never empty list #'.($i+1),
        $throws(static fn()=>$rows->invoke($repo,'SELECT protected_rows')));
}
$db->rows=[];
$check('Valid zero rows remains empty', $rows->invoke($repo,'SELECT rows')===[]);
$db->rows=[['id'=>1],['id'=>2]];
$check('Valid ordered list is unchanged',
    $rows->invoke($repo,'SELECT rows')===[['id'=>1],['id'=>2]]);
$db->rows=[['id'=>1],false];
$check('Partly malformed list cannot silently drop a row',
    $throws(static fn()=>$rows->invoke($repo,'SELECT rows')));
$db->rows=[17=>['id'=>17],18=>['id'=>18]];
$check('Sparse row keys become packed without losing records',
    $rows->invoke($repo,'SELECT rows')===[['id'=>17],['id'=>18]]);
foreach ([false,0,1] as $value) {
    $db->deleteResult=$value;
    $failed=$throws(static fn()=>$del->invoke($repo,'wp_cf02_messages',['case_uuid'=>'X']));
    $check('DELETE ' . var_export($value,true) . ' false-is-error only',
        $value===false ? $failed : !$failed);
}
foreach ([false,0,1] as $value) {
    $db->queryResult=$value;
    $failed=$throws(static fn()=>$query->invoke($repo,'DELETE FROM protected'));
    $check('Purge SQL ' . var_export($value,true) . ' false-is-error only',
        $value===false ? $failed : !$failed);
}
$db->deleteResult=1;$db->queryResult=0;$db->rows=[];
foreach ([null,false,'',true,-1,'3',3,'garbled'] as $value) {
    $db->holdCount=$value;$db->queries=[];$db->deleteCalls=0;
    $failed=$throws(static fn()=>$repo->purgeCase('CF02-CASE-TEST',
        ['all_targets_reconciled'=>true],new DateTimeImmutable('2026-10-21T06:00:00Z')));
    $check('Legal hold unknown/active ' . var_export($value,true) . ' blocks purge before transaction',
        $failed && $db->queries===[] && $db->deleteCalls===0);
}
$db->holdCount='0';$db->rows=false;$db->queries=[];
$failed=$throws(static fn()=>$repo->purgeCase('CF02-CASE-TEST',
    ['all_targets_reconciled'=>true],new DateTimeImmutable('2026-10-21T06:00:00Z')));
$check('Retention inventory read failure rolls back before deletion',
    $failed && $db->queries===['START TRANSACTION','ROLLBACK'] && $db->deleteCalls===0);
$source=(string)file_get_contents(dirname(__DIR__).'/src/Infrastructure/WordPress/OperationsRepository.php');
$start=strpos($source,'public function purgeCase(');
$end=strpos($source,'public function dueRetention(',$start);
$purge=substr($source,$start,$end-$start);
$check('All child table deletes use checked purge helper',
    !str_contains($purge,"$"."this->wpdb->delete($"."this->tables['inbound']") &&
    str_contains($purge,"$"."this->purgeDelete($"."this->tables['inbound']"));
$check('Purge result event is in the same transaction as case deletion',
    strpos($purge,"SupportRetentionPurgeCompleted") < strrpos($purge,'});')
    && strrpos($purge,'});') > strpos($purge,'Canonical case purge failed.'));
$check('All purge raw SQL DML use guarded helper',
    !str_contains($purge,"$"."this->wpdb->query(\"DELETE") &&
    !str_contains($purge,"$"."this->wpdb->query($"."this->wpdb->prepare(\"DELETE"));
printf("C2-S retention fail-closed test: %d pass, %d fail.\n",$pass,count($fail));
exit($fail===[]?0:1);
