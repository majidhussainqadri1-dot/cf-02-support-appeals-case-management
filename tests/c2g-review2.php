<?php

declare(strict_types=1);
ini_set('assert.exception','1');assert_options(ASSERT_ACTIVE,1);assert_options(ASSERT_EXCEPTION,1);
require_once dirname(__DIR__).'/src/Autoload.php';\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');

use Sabri\CF02\Migration\DualReadReconciler;

$failures=[];$test=static function(string $n,callable $c)use(&$failures):void{try{$c();fwrite(STDOUT,"PASS {$n}\n");}catch(Throwable $e){$failures[]=$n.': '.$e->getMessage();fwrite(STDERR,"FAIL {$n}: {$e->getMessage()}\n");}};

$test('strict dual read catches type drift not only display equality',static function():void{
    $result=(new DualReadReconciler())->compare(['sla'=>120],['sla'=>'120']);
    assert($result['status']==='diverged');
});

$test('unexplained appeal divergence blocks cutover even with record parity',static function():void{
    $reconciler=new DualReadReconciler();$parity=$reconciler->compare(['state'=>'open'],['state'=>'open']);
    $decision=$reconciler->cutoverDecision([$parity],1,1,0,1,true);
    assert(!$decision['allowed']);
});

$test('cutover without rollback rehearsal fails closed',static function():void{
    $reconciler=new DualReadReconciler();$parity=$reconciler->compare(['state'=>'open'],['state'=>'open']);
    $decision=$reconciler->cutoverDecision([$parity],1,1,0,0,false);
    assert(!$decision['allowed']);
});

if($failures!==[]){exit(1);}fwrite(STDOUT,"All CF-02 C2-G second-review adversarial tests passed.\n");
