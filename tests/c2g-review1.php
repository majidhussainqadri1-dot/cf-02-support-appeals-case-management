<?php

declare(strict_types=1);
ini_set('assert.exception','1');assert_options(ASSERT_ACTIVE,1);assert_options(ASSERT_EXCEPTION,1);
require_once dirname(__DIR__).'/src/Autoload.php';\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');

use Sabri\CF02\Domain\SupportCaseId;
use Sabri\CF02\Migration\DualReadReconciler;
use Sabri\CF02\Migration\MigrationLedger;
use Sabri\CF02\Migration\MigrationRecord;

$failures=[];$test=static function(string $n,callable $c)use(&$failures):void{try{$c();fwrite(STDOUT,"PASS {$n}\n");}catch(Throwable $e){$failures[]=$n.': '.$e->getMessage();fwrite(STDERR,"FAIL {$n}: {$e->getMessage()}\n");}};

$test('migration mapping cannot be rebound to another target',static function():void{
    $at=new DateTimeImmutable('2026-08-04T07:30:00+05:00');$ledger=new MigrationLedger();
    $ledger->record(new MigrationRecord('shared_support','legacy-2',1,SupportCaseId::generate(),hash('sha256','s'),hash('sha256','t1'),'migrated',$at));
    $thrown=false;
    try{$ledger->record(new MigrationRecord('shared_support','legacy-2',1,SupportCaseId::generate(),hash('sha256','s'),hash('sha256','t2'),'migrated',$at));}catch(DomainException){$thrown=true;}
    assert($thrown);
});

$test('incomplete migration count blocks cutover',static function():void{
    $result=(new DualReadReconciler())->cutoverDecision([],10,9,0,0,true);
    assert(!$result['allowed']);
});

$test('failed records remain isolated and observable',static function():void{
    $record=new MigrationRecord('shared_support','legacy-3',1,SupportCaseId::generate(),hash('sha256','s3'),hash('sha256','t3'),'failed',new DateTimeImmutable(),'mapping_error');
    $ledger=new MigrationLedger();$ledger->record($record);assert(count($ledger->failures())===1);
});

if($failures!==[]){exit(1);}fwrite(STDOUT,"All CF-02 C2-G first-review regressions passed.\n");
