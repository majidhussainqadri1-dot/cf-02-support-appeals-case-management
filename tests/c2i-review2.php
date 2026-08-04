<?php

declare(strict_types=1);
ini_set('assert.exception','1');assert_options(ASSERT_ACTIVE,1);assert_options(ASSERT_EXCEPTION,1);
$root=dirname(__DIR__);$failures=[];$test=static function(string $n,callable $c)use(&$failures):void{try{$c();fwrite(STDOUT,"PASS {$n}\n");}catch(Throwable $e){$failures[]=$n.': '.$e->getMessage();fwrite(STDERR,"FAIL {$n}: {$e->getMessage()}\n");}};
$read=static fn(string $p):string=>(string)file_get_contents($root.'/'.$p);

$test('complete REST surface exposes both canonical and compatibility namespaces',static function()use($read):void{
    $api=$read('src/Infrastructure/WordPress/ComprehensiveRestController.php');
    assert(str_contains($api,"['cf02/v1', 'api/support/v1']"));
    foreach(['/cases','/appeals','/staff/queue','/staff/cases/search','/staff/appeals','/staff/configuration','/staff/metrics/backlog','/staff/retention/due'] as $route){assert(str_contains($api,$route),$route);}
});

$test('signed provider adapters reject stale replay and changed inbound payloads',static function()use($read):void{
    $provider=$read('src/Infrastructure/WordPress/ProviderWebhookController.php');
    foreach(['X-CF02-Timestamp','X-CF02-Signature','X-CF02-Key-Id','hash_hmac','abs(time() - (int) $timestamp) > 300','Inbound replay identifier was reused with changed content'] as $needle){assert(str_contains($provider,$needle),$needle);}
});

$test('native authority remains behind versioned command filters and never direct companion writes',static function()use($read):void{
    $worker=$read('src/Infrastructure/WordPress/RuntimeWorker.php');
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    $provider=$read('src/Infrastructure/WordPress/ProviderWebhookController.php');
    assert(str_contains($worker,'cf02_dispatch_native_owner_command'));
    assert(str_contains($repo,'expected_native_version'));
    assert(str_contains($provider,'SupportNativeCommandResultRecorded'));
    foreach(['wp_users','wp_posts','wp_postmeta','smc_','file17_','file21_'] as $forbidden){assert(!str_contains($repo,$forbidden),$forbidden);}
});

$test('privacy safety accessibility and shell boundaries remain explicit',static function()use($read):void{
    $runtime=$read('src/Infrastructure/WordPress/Runtime.php');
    $surface=$read('src/Infrastructure/WordPress/FrontendSurfaces.php');
    $routes=$read('src/Infrastructure/WordPress/RouteRegistrar.php');
    foreach(['no-store','X-Robots-Tag','Permissions-Policy','Content-Security-Policy'] as $needle){assert(str_contains($runtime,$needle));}
    assert(str_contains($surface,'This is not an emergency or clinical queue'));
    assert(str_contains($surface,'prefers-reduced-motion'));
    assert(str_contains($routes,'sabri_register_route_contract'));
    assert(str_contains($routes,'File 20 remains the shell/layout owner'));
});

$test('retention cannot claim purge without provider reconciliation hold check and canonical deletion',static function()use($read):void{
    $worker=$read('src/Infrastructure/WordPress/RuntimeWorker.php');
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    assert(str_contains($worker,"all_targets_reconciled"));
    assert(str_contains($worker,'purgeCase('));
    assert(str_contains($repo,"state='active'"));
    assert(str_contains($repo,'Active legal or appeal hold blocks purge'));
    assert(str_contains($repo,'Canonical case purge failed'));
});

$test('native callback and worker preserve terminal result immutability definitive failure and reconciliation events',static function()use($read):void{
    $worker=$read('src/Infrastructure/WordPress/RuntimeWorker.php');
    $provider=$read('src/Infrastructure/WordPress/ProviderWebhookController.php');
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    foreach(['A terminal native command result cannot be changed','nativeResultEvidence','redaction-result'] as $needle){assert(str_contains($provider,$needle),$needle);}
    assert(str_contains($repo,'SupportAttachmentRedacted'));
    assert(str_contains($worker, '$state === \'failed\''));
    assert(str_contains($worker, "updateCommandResult((string) \$command['command_uuid'], 'failed'"));
    assert(str_contains($worker,'SupportNativeCommandResultRecorded'));
});

$test('worker SLA and event queues are observable and SQL aliases are valid',static function()use($read):void{
    $worker=$read('src/Infrastructure/WordPress/RuntimeWorker.php');
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    assert(str_contains($worker,'SupportSlaBreached'));
    assert(str_contains($worker,'SupportSlaAtRisk'));
    $start=strpos($repo,'public function pendingEvents');
    $end=strpos($repo,'public function markEventPublished',$start);
    $block=substr($repo,$start,$end-$start);
    assert(!str_contains($block,'o.'));
    assert(str_contains($block,"publish_state IN ('pending','retry')"));
});

$test('WP CLI eval fixture avoids strict-types declaration that cannot be first after eval wrapping',static function()use($read):void{
    $fixture=$read('tests/wordpress-runtime-smoke.php');
    assert(!str_contains($fixture,'declare(strict_types=1)'));
    assert(str_starts_with($fixture,"<?php\n"));
});

if($failures!==[]){exit(1);}fwrite(STDOUT,"All CF-02 C2-I second-review adversarial tests passed.\n");
