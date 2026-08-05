<?php

declare(strict_types=1);
ini_set('assert.exception','1');assert_options(ASSERT_ACTIVE,1);assert_options(ASSERT_EXCEPTION,1);
require_once dirname(__DIR__).'/src/Autoload.php';\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');

use Sabri\CF02\Operations\TrainingRegister;
use Sabri\CF02\Resilience\LoadBudget;
use Sabri\CF02\Security\DataCipher;

$failures=[];$test=static function(string $n,callable $c)use(&$failures):void{try{$c();fwrite(STDOUT,"PASS {$n}\n");}catch(Throwable $e){$failures[]=$n.': '.$e->getMessage();fwrite(STDERR,"FAIL {$n}: {$e->getMessage()}\n");}};

$test('tampered encrypted payload fails authentication',static function():void{
    $cipher=new DataCipher(str_repeat('key-material-',8));$encrypted=$cipher->encrypt('private');
    $tampered=substr($encrypted,0,-2).'AA';$thrown=false;try{$cipher->decrypt($tampered);}catch(RuntimeException){$thrown=true;}assert($thrown);
});

$test('load budget rejects impossible configuration',static function():void{
    $thrown=false;try{new LoadBudget(0,10,0,-1.0,0);}catch(InvalidArgumentException){$thrown=true;}assert($thrown);
});

$test('expired training removes operational readiness',static function():void{
    $at=new DateTimeImmutable('2026-08-04T09:00:00+05:00');$register=new TrainingRegister();
    $register->certify('staff:1',['privacy','security','appeals_fairness','emergency_diversion','accessibility','sla_operations'],$at->modify('+1 day'),'trainer:1',$at);
    assert(!$register->readiness(['staff:1'],$at->modify('+2 days'))['ready']);
});

if($failures!==[]){exit(1);}fwrite(STDOUT,"All CF-02 C2-H second-review adversarial tests passed.\n");
