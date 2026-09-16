<?php

declare(strict_types=1);
ini_set('assert.exception','1');
assert_options(ASSERT_ACTIVE,1);
assert_options(ASSERT_EXCEPTION,1);
require_once dirname(__DIR__).'/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');

use Sabri\CF02\Application\RuntimeWorkflowPolicy;
use Sabri\CF02\Attachment\AttachmentState;
use Sabri\CF02\Attachment\AttachmentStateMachine;
use Sabri\CF02\Domain\CaseState;
use Sabri\CF02\Domain\CaseStateMachine;

$root=dirname(__DIR__);
$failures=[];
$test=static function(int $round,string $name,callable $callback)use(&$failures):void{
    try{$callback();fwrite(STDOUT,sprintf("PASS REVIEW %02d %s\n",$round,$name));}
    catch(Throwable $e){$failures[]=sprintf('%02d %s: %s',$round,$name,$e->getMessage());fwrite(STDERR,sprintf("FAIL REVIEW %02d %s: %s\n",$round,$name,$e->getMessage()));}
};
$read=static fn(string $path):string=>(string)file_get_contents($root.'/'.$path);

$test(24,'domain and runtime state laws use one canonical vocabulary and native appeal gate',static function()use($read):void{
    assert(CaseState::WaitingForUser->value==='waiting_user');
    assert(CaseState::WaitingForProvider->value==='waiting_provider');
    assert(CaseState::Withdrawn->value==='withdrawn');
    $cases=new CaseStateMachine();
    assert($cases->canTransition(CaseState::New,CaseState::Withdrawn));
    assert($cases->canTransition(CaseState::Withdrawn,CaseState::Reopened));
    assert(RuntimeWorkflowPolicy::caseTransitions()['waiting_user']===['in_progress','resolved','withdrawn']);
    assert(RuntimeWorkflowPolicy::appealTransitions()['under_review']===['native_decision_pending']);
    assert(RuntimeWorkflowPolicy::appealTransitions()['native_decision_pending']===['decided']);
    $attachments=new AttachmentStateMachine();
    assert($attachments->canTransition(AttachmentState::Scanned,AttachmentState::Redacted));
    assert(!$attachments->canTransition(AttachmentState::Rejected,AttachmentState::Expired));
    assert($attachments->canTransition(AttachmentState::Superseded,AttachmentState::Purged));
    assert(RuntimeWorkflowPolicy::attachmentTransitions()['scanned']===['available','rejected','redacted']);
    assert(RuntimeWorkflowPolicy::attachmentTransitions()['rejected']===['purged']);
    assert(RuntimeWorkflowPolicy::attachmentTransitions()['superseded']===['expired','purged']);
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    assert(str_contains($repo, "RuntimeWorkflowPolicy::assertAttachment(\$fromState, 'redacted')"));
    assert(str_contains($repo, "'state' => \$fromState"));
    $runtime=$read('src/Application/RuntimeWorkflowPolicy.php');
    assert(!str_contains($runtime,"'under_review' => ['native_decision_pending', 'decided']"));
});

$test(25,'object-level authorization keeps appeal review assigned and audit sampling non-global',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    assert(!str_contains($repo, "hasAnyCapability('queue.manage', 'audit.sample.read')"));
    assert(!str_contains($repo, "'case.search.scoped', 'queue.manage', 'audit.sample.read'"));
    assert(str_contains($repo, "if (\$allowStaff && \$context->hasCapability('appeal.queue.read'))"));
    assert(str_contains($repo, "hasAnyCapability('appeal.review', 'appeal.decision', 'appeal.native.request', 'appeal.implementation.confirm')"));
    assert(str_contains($repo, "!hash_equals((string) \$row['reviewer_ref'], \$context->actorReference())"));
});

if($failures!==[]){fwrite(STDERR,implode("\n",$failures)."\n");exit(1);}fwrite(STDOUT,"CF-02 R24-R33 regression register passed through R25.\n");
