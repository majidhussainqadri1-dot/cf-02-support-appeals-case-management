<?php

declare(strict_types=1);
ini_set('assert.exception','1');assert_options(ASSERT_ACTIVE,1);assert_options(ASSERT_EXCEPTION,1);
require_once dirname(__DIR__).'/src/Autoload.php';\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');

use Sabri\CF02\Infrastructure\WordPress\Schema;
use Sabri\CF02\Release\ReleaseGate;
use Sabri\CF02\Resilience\RecoveryEvidence;

$failures=[];$test=static function(string $n,callable $c)use(&$failures):void{try{$c();fwrite(STDOUT,"PASS {$n}\n");}catch(Throwable $e){$failures[]=$n.': '.$e->getMessage();fwrite(STDERR,"FAIL {$n}: {$e->getMessage()}\n");}};

$test('schema rejects injected table prefix',static function():void{
    $thrown=false;try{Schema::statements('wp_;DROP_','');}catch(InvalidArgumentException){$thrown=true;}assert($thrown);
});

$test('recovery evidence cannot omit provider mapping or audit',static function():void{
    $thrown=false;try{RecoveryEvidence::create(new DateTimeImmutable(),0,0,['database','object_storage','configuration','queues'],true,true,true,true,'artifact');}catch(InvalidArgumentException){$thrown=true;}assert($thrown);
});

$test('release evidence defaults missing fields to blocked',static function():void{
    $decision=(new ReleaseGate())->evaluate(['all_requirements_coded'=>true],new DateTimeImmutable());assert(!$decision['allowed']);assert(count($decision['missing'])>10);
});

if($failures!==[]){exit(1);}fwrite(STDOUT,"All CF-02 C2-H first-review regressions passed.\n");
