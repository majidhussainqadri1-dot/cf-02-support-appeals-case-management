<?php

declare(strict_types=1);
ini_set('assert.exception', '1');
assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_EXCEPTION, 1);
require_once dirname(__DIR__) . '/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__) . '/src');

use Sabri\CF02\Automation\AutomationAction;
use Sabri\CF02\Automation\AutomationGuard;
use Sabri\CF02\Configuration\ConfigurationRegistry;
use Sabri\CF02\Configuration\ConfigurationSnapshot;
use Sabri\CF02\Delivery\DeliveryOutbox;
use Sabri\CF02\Delivery\OutboxMessage;
use Sabri\CF02\Domain\SupportCaseId;
use Sabri\CF02\Quality\QualityReview;

$failures=[];
$test=static function(string $n,callable $c)use(&$failures):void{try{$c();fwrite(STDOUT,"PASS {$n}\n");}catch(Throwable $e){$failures[]=$n.': '.$e->getMessage();fwrite(STDERR,"FAIL {$n}: {$e->getMessage()}\n");}};

$test('low confidence suggestion requires human review and is not allowed',static function():void{
    $r=(new AutomationGuard())->decide(AutomationAction::Classify,0.40,'model-v1',false);
    assert(!$r['allowed']&&$r['human_review_required']);
});

$test('quality rubric cannot omit security or accessibility',static function():void{
    $thrown=false;
    try{QualityReview::create(SupportCaseId::generate(),'reviewer:1','random',['accuracy'=>90,'empathy'=>90,'compliance'=>90],[],new DateTimeImmutable(),true);}catch(InvalidArgumentException){$thrown=true;}
    assert($thrown);
});

$test('high risk configuration requires independent dual approval',static function():void{
    $at=new DateTimeImmutable('2026-08-04T06:00:00+05:00');
    $snapshot=ConfigurationSnapshot::create('CF02-CFG-HIGH',1,'staged',['categories'=>['technical']],['approver:1'],'creator:1',$at);
    $thrown=false;
    try{(new ConfigurationRegistry())->stage($snapshot,true);}catch(DomainException){$thrown=true;}
    assert($thrown);
});

$test('outbox idempotency cannot bind changed payload',static function():void{
    $case=SupportCaseId::generate();$at=new DateTimeImmutable('2026-08-04T06:10:00+05:00');$outbox=new DeliveryOutbox();
    $outbox->enqueue(OutboxMessage::create($case,'email','user:1','case.receipt','one','outbox-review-0001',$at,false));
    $thrown=false;
    try{$outbox->enqueue(OutboxMessage::create($case,'email','user:1','case.receipt','two','outbox-review-0001',$at,false));}catch(DomainException){$thrown=true;}
    assert($thrown);
});

if($failures!==[]){exit(1);}fwrite(STDOUT,"All CF-02 C2-F first-review regressions passed.\n");
