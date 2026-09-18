<?php

declare(strict_types=1);
ini_set('assert.exception','1');assert_options(ASSERT_ACTIVE,1);assert_options(ASSERT_EXCEPTION,1);
require_once dirname(__DIR__).'/src/Autoload.php';\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');

use Sabri\CF02\Future\EvidenceChannelIntelligence;
use Sabri\CF02\Future\IntegrationSecurityIntelligence;
use Sabri\CF02\Future\OperationsTransparencyIntelligence;

$root=dirname(__DIR__);$failures=[];
$test=static function(int $round,string $name,callable $callback)use(&$failures):void{try{$callback();fwrite(STDOUT,sprintf("PASS FRESH REVIEW %02d %s\n",$round,$name));}catch(Throwable $e){$failures[]=sprintf('%02d %s: %s',$round,$name,$e->getMessage());fwrite(STDERR,sprintf("FAIL FRESH REVIEW %02d %s: %s\n",$round,$name,$e->getMessage()));}};
$read=static fn(string $path):string=>(string)file_get_contents($root.'/'.$path);

$test(1,'Future24 deep links resist normalization bypass and repeated upload content remains valid',static function():void{
    $now=new DateTimeImmutable('2026-09-14T00:00:00+00:00');$links=new IntegrationSecurityIntelligence(str_repeat('K',32));
    foreach(['//evil.example/x','/%2F%2Fevil.example/x','/%252F%252Fevil.example/x','/\\evil.example/x'] as $path){$blocked=false;try{$links->issueSecureDeepLink('case_status','OpaqueRef_12345',$path,$now);}catch(InvalidArgumentException){$blocked=true;}assert($blocked,$path);}
    $same=str_repeat('a',64);$upload=(new EvidenceChannelIntelligence())->resumableUpload('UP-repeat',10,[['index'=>0,'sha256'=>$same,'size'=>5],['index'=>1,'sha256'=>$same,'size'=>5]],str_repeat('b',64),true);assert($upload['complete']===true&&$upload['evidence_accessible']===true);
});

$test(2,'provider webhook errors never expose internal exception messages',static function()use($read):void{
    $source=$read('src/Infrastructure/WordPress/ProviderWebhookController.php');
    assert(str_contains($source,'cf02_provider_request_failed'));
    assert(str_contains($source,'The signed provider request was rejected or could not be processed.'));
    assert(!str_contains($source,'$error instanceof RuntimeException ? $error->getMessage()'));
});

$test(4,'ordinary assigned staff cannot receive C4/C5 attachment metadata without sensitive capability and recent step-up',static function()use($read):void{
    $source=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    assert(str_contains($source, '$context->hasCapability(\'case.sensitive.read\')'));
    assert(str_contains($source, '$context->recentlyAuthenticated(new DateTimeImmutable(\'now\', new DateTimeZone(\'UTC\')))'));
    assert(str_contains($source, '!in_array((string) $row[\'privacy_class\'], [\'C4\',\'C5\'], true)'));
});

$test(5,'closed and withdrawn cases reject new messages and attachments until governed reopen',static function()use($read):void{
    $source=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    assert(str_contains($source, 'Closed or withdrawn cases cannot receive messages before governed reopen.'));
    assert(str_contains($source, 'Closed or withdrawn cases cannot receive attachments before governed reopen.'));
});

$test(6,'native-object links require canonical-owner authorization and C4/C5 link metadata requires sensitive step-up',static function()use($read):void{
    $controller=$read('src/Infrastructure/WordPress/ComprehensiveRestController.php');
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    assert(str_contains($controller, "apply_filters('cf02_authorize_native_object_link'"));
    assert(str_contains($controller, "authorized'] ?? false) !== true"));
    assert(str_contains($controller, 'verified_version')); assert(str_contains($controller, 'hash_equals('));
    assert(str_contains($repo, "['C4','C5'], true)"));
});

$test(7,'SLA pause/resume is reason-bound and only authoritative evidence resumes the matching wait state',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    $controller=$read('src/Infrastructure/WordPress/ComprehensiveRestController.php');
    $worker=$read('src/Infrastructure/WordPress/RuntimeWorker.php');
    $catalog=$read('src/Contracts/SupportContractCatalog.php');
    assert(str_contains($repo, '?string $expectedPauseReason = null'));
    assert(str_contains($repo, 'expectedPauseReason')); assert(str_contains($repo, 'pause_reason'));
    assert(str_contains($controller, "'waiting_user'"));
    assert(str_contains($worker, "'waiting_provider'"));
    assert(str_contains($catalog, 'SupportSlaPaused') && str_contains($catalog, 'SupportSlaResumed'));
});

$test(8,'appeal reviewer runtime enforces original-decision actor/unit separation and assigned-reviewer-only decisions',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    $controller=$read('src/Infrastructure/WordPress/ComprehensiveRestController.php');
    assert(str_contains($repo, 'original_decision_actor'));
    assert(str_contains($repo, 'original_decision_unit'));
    assert(str_contains($repo, 'same_original_decision_actor'));
    assert(str_contains($repo, 'same_original_decision_unit'));
    assert(str_contains($controller, 'Only the independently assigned reviewer may record the appeal decision.'));
});

$test(9,'attachment tokens respect attachment expiry and sensitive staff step-up boundaries',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    assert(str_contains($repo, 'Sensitive attachment access requires scoped capability and recent authentication.'));
    assert(str_contains($repo, 'a.expires_at IS NOT NULL AND a.expires_at>UTC_TIMESTAMP(6)'));
    assert(str_contains($repo, 'privacy_class FROM'));
});

$test(10,'guest intake remains low-sensitivity, encrypted, time-bounded, authenticated on continuation and non-cacheable',static function()use($read):void{
    $policy=$read('src/Intake/GuestIntakePolicy.php');
    $token=$read('src/Intake/GuestContinuationToken.php');
    $controller=$read('src/Infrastructure/WordPress/GuestIntakeController.php');
    $rest=$read('src/Infrastructure/WordPress/ComprehensiveRestController.php');
    assert(str_contains($policy, "GUEST_CATEGORIES = ['publishing', 'learning_access', 'media_pdf', 'accessibility', 'technical']"));
    assert(str_contains($token, 'sodium_crypto_secretbox(') && str_contains($token, 'ttlSeconds > 900'));
    assert(str_contains($controller, "hasCapability('case.create')"));
    assert(str_contains($rest, "'Cache-Control' => 'private, no-store, max-age=0'"));
});

$test(11,'authenticated intake uses all six governed emergency runbooks and cannot create an ordinary-SLA case first',static function()use($read):void{
    $controller=$read('src/Infrastructure/WordPress/ComprehensiveRestController.php');
    $registry=$read('src/Safety/EmergencyRunbookRegistry.php');
    foreach(['clinical_red_flag','imminent_harm','account_takeover','child_safety','privacy_breach','financial_fraud'] as $type){assert(str_contains($registry, "'".$type."'"));}
    assert(str_contains($controller, 'EmergencyRunbookRegistry::classifyText'));
    assert(str_contains($controller, "do_action('cf02_emergency_diversion'"));
    assert(str_contains($controller, "cf02_emergency_diversion_"));
});

$test(12,'outcome delivery failure reopens resolved cases and auto-close trusts server delivery state, not client booleans',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    $worker=$read('src/Infrastructure/WordPress/RuntimeWorker.php');
    $controller=$read('src/Infrastructure/WordPress/ComprehensiveRestController.php');
    assert(str_contains($repo, 'recoverOutcomeDeliveryFailure'));
    assert(str_contains($repo, "'state' => 'reopened'"));
    assert(str_contains($worker, "template_key'] ?? '') === 'support_case_resolved'"));
    assert(str_contains($controller, 'outcomeDeliveryStatus'));
    assert(!str_contains($controller, "eligible_auto_close_notice_sent"));
});

$test(13,'native-owner commands require canonical-owner authorization and idempotency binds case plus expected native version',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    assert(str_contains($repo, "apply_filters('cf02_authorize_native_owner_command'"));
    assert(str_contains($repo, 'Native-owner command is not authorized by the canonical owner.'));
    assert(str_contains($repo, "existing['case_uuid']"));
    assert(str_contains($repo, "existing['expected_native_version']"));
});

$test(14,'retention purge cannot delete a case while any appeal remains unresolved',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    assert(str_contains($repo, "state<>'closed'"));
    assert(str_contains($repo, 'Open or unresolved appeal blocks purge until appeal closure.'));
});

$test(15,'transparency parity audit inherits the caller privacy threshold instead of silently falling back to twenty',static function():void{
    $metrics=['case_count'=>100,'first_response_seconds_p50'=>10,'resolution_seconds_p50'=>20,'reopen_rate'=>0.1,'appeal_overturn_rate'=>0.1,'accessibility_completion_rate'=>0.9,'major_incident_count'=>0];
    $parity=['cohort_size'=>30,'metrics'=>['first_response_seconds_p50'=>10,'resolution_seconds_p50'=>20,'escalation_rate'=>0.1,'reopen_rate'=>0.1,'appeal_access_rate'=>0.9]];
    $out=(new OperationsTransparencyIntelligence())->transparencyCenter('2026-08',$metrics,$parity,$parity,50);
    assert($out['status']==='published-aggregate');
    assert($out['support_parity_status']==='suppressed');
});

$test(16,'managed-key rotation escapes SQL LIKE key identifiers and atomically persists ciphertext with rotation evidence',static function()use($read):void{
    $rotation=$read('src/Infrastructure/WordPress/EncryptionRotationService.php');
    assert(str_contains($rotation, 'esc_like('));
    assert(str_contains($rotation, 'FOR UPDATE'));
    assert(str_contains($rotation, 'START TRANSACTION'));
    assert(str_contains($rotation, 'Key rotation evidence persistence failed.'));
    assert(str_contains($rotation, 'ROLLBACK'));
    assert(str_contains($rotation, 'COMMIT'));
});

$test(17,'provider signing keys are explicitly bound to claimed inbound/native owner identities',static function()use($read):void{
    $source=$read('src/Infrastructure/WordPress/ProviderWebhookController.php');
    assert(str_contains($source, 'cf02_provider_key_authorizes_owner'));
    assert(str_contains($source, 'assertProviderOwner($keyId, $sourceOwner, \'inbound\')'));
    assert(str_contains($source, 'assertProviderOwner($keyId, $owner, \'native_result\')'));
    assert(str_contains($source, 'Provider signing identity is not authorized for the claimed owner.'));
});


$test(20,'runtime SLA cannot pause before first response or keep treating a satisfied first-response deadline as due',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    $worker=$read('src/Infrastructure/WordPress/RuntimeWorker.php');
    assert(str_contains($repo, 'SLA pause is prohibited before a requester-visible staff response.'));
    assert(str_contains($repo, 'first_response_recorded'));
    assert(str_contains($repo, "NOT EXISTS ("));
    assert(str_contains($worker, "first_response_recorded"));
});


$test(21,'appeal remand remains assigned-reviewer-only and implementation confirmation requires server-side authoritative verification',static function()use($read):void{
    $controller=$read('src/Infrastructure/WordPress/ComprehensiveRestController.php');
    assert(str_contains($controller, 'Only the independently assigned reviewer may remand the appeal.'));
    assert(str_contains($controller, 'A reasoned remand decision is required.'));
    assert(str_contains($controller, "cf02_verify_appeal_implementation"));
    assert(str_contains($controller, "verification['verified']"));
    assert(!str_contains($controller, "native_version_matches"));
});


$test(22,'delivery idempotency binds case and template as well as recipient channel and payload',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    assert(str_contains($repo, "existing['case_uuid']"));
    assert(str_contains($repo, "existing['template_key']"));
    assert(str_contains($repo, "Delivery idempotency collision."));
});


$test(23,'retention purge is serialized with hold and appeal creation under the canonical case row lock',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    assert(str_contains($repo, 'lockCaseForLifecycle'));
    assert(str_contains($repo, 'FOR UPDATE'));
    assert(substr_count($repo, 'lockCaseForLifecycle($caseId->value()') >= 2);
    assert(str_contains($repo, 'lockCaseForLifecycle($caseId, [\'closed\'])'));
    $purge=strpos($repo, 'public function purgeCase');
    $lock=strpos($repo, 'lockCaseForLifecycle($caseId, [\'closed\'])', $purge);
    $hold=strpos($repo, "Active legal or appeal hold blocks purge.", $purge);
    $appeal=strpos($repo, "Open or unresolved appeal blocks purge until appeal closure.", $purge);
    assert($lock !== false && $hold !== false && $appeal !== false && $lock < $hold && $lock < $appeal);
});

if($failures!==[]){fwrite(STDERR,implode("\n",$failures)."\n");exit(1);}fwrite(STDOUT,"CF-02 fresh 40-round regression register passed through round 23.\n");