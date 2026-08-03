<?php

declare(strict_types=1);
ini_set('assert.exception','1');assert_options(ASSERT_ACTIVE,1);assert_options(ASSERT_EXCEPTION,1);
require_once dirname(__DIR__).'/src/Autoload.php';\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');

use Sabri\CF02\Automation\AutomationAction;
use Sabri\CF02\Automation\AutomationGuard;
use Sabri\CF02\Configuration\ConfigurationSnapshot;
use Sabri\CF02\Delivery\OutboxMessage;
use Sabri\CF02\Domain\SupportCaseId;
use Sabri\CF02\Feedback\SatisfactionFeedback;
use Sabri\CF02\Security\ProgressiveRateLimiter;
use Sabri\CF02\Task\CaseTask;

$failures=[];$test=static function(string $n,callable $c)use(&$failures):void{try{$c();fwrite(STDOUT,"PASS {$n}\n");}catch(Throwable $e){$failures[]=$n.': '.$e->getMessage();fwrite(STDERR,"FAIL {$n}: {$e->getMessage()}\n");}};

$test('automation cannot auto close without governed human confirmation',static function():void{
    $blocked=(new AutomationGuard())->decide(AutomationAction::AutoClose,1.0,'model-v2',false,false);
    assert(!$blocked['allowed']);
    $allowed=(new AutomationGuard())->decide(AutomationAction::AutoClose,1.0,'model-v2',false,true);
    assert($allowed['allowed']&&$allowed['human_review_required']);
});

$test('configuration templates reject secret-bearing variables',static function():void{
    $thrown=false;
    try{ConfigurationSnapshot::create('CF02-CFG-BAD',1,'staged',['templates'=>['reply'=>'OTP {{otp}}']],['a:1','a:2'],'creator:1',new DateTimeImmutable());}catch(InvalidArgumentException){$thrown=true;}
    assert($thrown);
});

$test('opt-out feedback retains no rating or comment and ordinary feedback rejects secrets',static function():void{
    $feedback=new SatisfactionFeedback(SupportCaseId::generate(),'anon:1',null,null,true,new DateTimeImmutable());
    assert($feedback->optedOut()&&$feedback->rating()===null&&$feedback->comment()===null);
    $thrown=false;
    try{new SatisfactionFeedback(SupportCaseId::generate(),'anon:2',5,'OTP: 123456',false,new DateTimeImmutable());}catch(InvalidArgumentException){$thrown=true;}
    assert($thrown);
});

$test('repeated provider failure becomes observable dead letter',static function():void{
    $at=new DateTimeImmutable('2026-08-04T06:30:00+05:00');
    $message=OutboxMessage::create(SupportCaseId::generate(),'email','user:1','case.update','safe','outbox-dead-00001',$at,false);
    for($i=0;$i<7;$i++){$message->markAttempt($at->modify('+'.($i+1).' minutes'));$message->markFailed(true);}
    assert($message->status()==='dead_letter');
});

$test('progressive rate control challenges then blocks abuse without hiding emergency direction',static function():void{
    $limiter=new ProgressiveRateLimiter();$at=new DateTimeImmutable('2026-08-04T06:40:00+05:00');$last=[];
    for($i=0;$i<6;$i++){$last=$limiter->decide('guest:hash-1','case.create',$at->modify('+'.$i.' seconds'),false,true);}
    assert(!$last['allowed']);assert($last['challenge_required']);assert($last['emergency_diversion_visible']);
});

$test('case tasks require explicit dependency outcome and optimistic concurrency',static function():void{
    $at=new DateTimeImmutable('2026-08-04T06:50:00+05:00');
    $task=CaseTask::create(SupportCaseId::generate(),'native_owner_check','agent:1','command:1',$at,$at->modify('+1 day'));
    $task->block('command:1',1);$task->resume(2);$task->complete('outcome:1',$at->modify('+1 hour'),3);
    assert($task->state()==='completed');assert($task->outcomeReference()==='outcome:1');
});

if($failures!==[]){exit(1);}fwrite(STDOUT,"All CF-02 C2-F second-review adversarial tests passed.\n");
