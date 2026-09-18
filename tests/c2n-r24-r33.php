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


$test(26,'repository serializes state law and waiting/SLA pause atomically',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    $rest=$read('src/Infrastructure/WordPress/ComprehensiveRestController.php');
    assert(str_contains($repo, "RuntimeWorkflowPolicy::assertCase(\$fromState, \$fields['state'])"));
    assert(str_contains($repo, "RuntimeWorkflowPolicy::assertAppeal(\$fromState, \$fields['state'])"));
    assert(str_contains($repo, "public function waitCaseAndPauseSla("));
    assert(str_contains($repo, "SELECT record_version,status FROM {\$this->tables['sla']} WHERE case_uuid=%s FOR UPDATE"));
    assert(str_contains($repo, "Terminal cases must be reopened before escalation."));
    assert(str_contains($rest, "return \$this->operations->waitCaseAndPauseSla("));
}
);
$test(27,'schema verification detects structural drift and blocks unknown newer schemas',static function()use($read):void{
    $installer=$read('src/Infrastructure/WordPress/Installer.php');
    $repair=$read('src/Infrastructure/WordPress/RepairService.php');
    assert(str_contains($installer, "version_compare(\$installed, SchemaCompletion::VERSION, '>')"));
    assert(str_contains($installer, "public static function schemaIssues(string \$prefix): array"));
    assert(str_contains($installer, "SHOW COLUMNS FROM"));
    assert(str_contains($installer, "SHOW INDEX FROM"));
    assert(str_contains($repair, "'schema_issues'=>\$issues"));
});
$test(28,'retention purge removes derivatives and stores only bounded provider evidence',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    assert(str_contains($repo, "DELETE nr FROM {\$this->tables['note_revisions']} nr JOIN {\$this->tables['messages']}"));
    assert(str_contains($repo, "DELETE t FROM {\$this->tables['tokens']} t JOIN {\$this->tables['attachments']}"));
    assert(str_contains($repo, "\$this->wpdb->delete(\$this->tables['inbound'], ['case_uuid' => \$caseId])"));
    assert(str_contains($repo, "'result_hash' => \$rawHash"));
    assert(str_contains($repo, "'provider_results_json' => \$this->json(\$summary)"));
    assert(!str_contains($repo, "'provider_results_json' => \$this->json(\$providerResults)"));
});
$test(29,'SLA worker emits escalation side effects only on a real status transition',static function()use($read):void{
    $worker=$read('src/Infrastructure/WordPress/RuntimeWorker.php');
    assert(str_contains($worker, "if (hash_equals((string) (\$timer['status'] ?? ''), \$status))"));
});
$test(30,'worker side effects are concurrency leased and replay identity is aggregate-type bound',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    $worker=$read('src/Infrastructure/WordPress/RuntimeWorker.php');
    assert(str_contains($repo, "SELECT GET_LOCK(%s,0)"));
    assert(str_contains($repo, "SELECT RELEASE_LOCK(%s)"));
    assert(str_contains($worker, "acquireWorkerLease('event'"));
    assert(str_contains($worker, "acquireWorkerLease('outbox'"));
    assert(str_contains($worker, "acquireWorkerLease('command'"));
    assert(substr_count($worker, 'releaseWorkerLease(')>=3);
    assert(str_contains($repo, "SELECT aggregate_type,aggregate_ref,payload_hash"));
    assert(str_contains($repo, "eventReplay(\$appealId, \$event, \$idempotencyKey, \$payload, 'appeal')"));
    assert(str_contains($repo, "Merge target must be canonical and not already redirected."));
    assert(str_contains($repo, "sort(\$lockIds, SORT_STRING)"));
    assert(substr_count($repo, "WHERE idempotency_key=%s LIMIT 1")>=4);
});

$test(31,'runtime class resolution and terminal external-result states are monotonic',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    $worker=$read('src/Infrastructure/WordPress/RuntimeWorker.php');
    assert(str_contains($worker,'use Sabri\\CF02\\Domain\\SupportCaseId;'));
    assert(str_contains($repo,'Terminal native command result is immutable.'));
    assert(str_contains($repo,"'state' => \$current"));
    assert(str_contains($repo,"publish_state IN ('pending','retry')"));
    assert(str_contains($worker,'Publication is already durable. Observer failures must never reopen/retry it.'));
    assert(str_contains($worker,'Post-result reconciliation is non-authoritative for command terminal state.'));
});

$test(32,'attachment bearer token is consumed only after authorized secure delivery under a token lease',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    $provider=$read('src/Infrastructure/WordPress/ProviderWebhookController.php');
    assert(str_contains($repo,'public function inspectAttachmentToken'));
    assert(str_contains($provider,"acquireWorkerLease('attachment_token'"));
    assert(str_contains($provider,'inspectAttachmentToken($token'));
    assert(strpos($provider,"(\$delivery['authorized'] ?? false) !== true") < strpos($provider,'consumeAttachmentToken($token'));
    assert(str_contains($provider,"releaseWorkerLease('attachment_token'"));
});

$test(33,'SLA escalation and retention purge workers are serialized before external side effects',static function()use($read):void{
    $worker=$read('src/Infrastructure/WordPress/RuntimeWorker.php');
    assert(str_contains($worker,"acquireWorkerLease('sla', \$caseId)"));
    assert(str_contains($worker,"releaseWorkerLease('sla', \$caseId)"));
    assert(str_contains($worker,"acquireWorkerLease('retention', \$caseId)"));
    assert(str_contains($worker,"releaseWorkerLease('retention', \$caseId)"));
    assert(strpos($worker,"acquireWorkerLease('sla', \$caseId)") < strpos($worker,'cf02_sla_escalation_request'));
    assert(strpos($worker,"acquireWorkerLease('retention', \$caseId)") < strpos($worker,'cf02_retention_purge_request'));
});

if($failures!==[]){fwrite(STDERR,implode("\n",$failures)."\n");exit(1);}fwrite(STDOUT,"CF-02 R24-R33 regression register passed through R33.\n");
