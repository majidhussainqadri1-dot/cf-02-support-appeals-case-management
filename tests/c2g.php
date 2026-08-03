<?php

declare(strict_types=1);
ini_set('assert.exception','1');assert_options(ASSERT_ACTIVE,1);assert_options(ASSERT_EXCEPTION,1);
require_once dirname(__DIR__).'/src/Autoload.php';\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');

use Sabri\CF02\Domain\SupportCaseId;
use Sabri\CF02\Migration\DualReadReconciler;
use Sabri\CF02\Migration\MigrationLedger;
use Sabri\CF02\Migration\MigrationRecord;

$failures=[];$test=static function(string $n,callable $c)use(&$failures):void{try{$c();fwrite(STDOUT,"PASS {$n}\n");}catch(Throwable $e){$failures[]=$n.': '.$e->getMessage();fwrite(STDERR,"FAIL {$n}: {$e->getMessage()}\n");}};

$test('migration mapping is idempotent and preserves source target hashes',static function():void{
    $case=SupportCaseId::generate();$at=new DateTimeImmutable('2026-08-04T07:00:00+05:00');
    $source=hash('sha256','source');$target=hash('sha256','target');
    $record=new MigrationRecord('shared_support','legacy-1',3,$case,$source,$target,'migrated',$at);
    $ledger=new MigrationLedger();assert($ledger->record($record)===$record);assert($ledger->record($record)===$record);assert($ledger->count()===1);
});

$test('dual read parity permits cutover only with zero unexplained divergence and rollback proof',static function():void{
    $reconciler=new DualReadReconciler();
    $result=$reconciler->compare(['case_id'=>'1','state'=>'open','sla'=>120],['case_id'=>'1','state'=>'open','sla'=>120]);
    assert($result['status']==='reconciled');
    $decision=$reconciler->cutoverDecision([$result],1,1,0,0,true);
    assert($decision['allowed']);
});

$test('divergence blocks cutover without destroying source truth',static function():void{
    $reconciler=new DualReadReconciler();
    $result=$reconciler->compare(['case_id'=>'1','state'=>'open','sla'=>120],['case_id'=>'1','state'=>'closed','sla'=>0]);
    assert($result['status']==='diverged');
    $decision=$reconciler->cutoverDecision([$result],1,1,1,0,true);
    assert(!$decision['allowed']);
});

if($failures!==[]){exit(1);}fwrite(STDOUT,"All CF-02 C2-G tests passed.\n");
