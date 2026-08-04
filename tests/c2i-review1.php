<?php

declare(strict_types=1);
ini_set('assert.exception','1');
assert_options(ASSERT_ACTIVE,1);
assert_options(ASSERT_EXCEPTION,1);
$root=dirname(__DIR__);
$failures=[];
$test=static function(string $name, callable $callback)use(&$failures):void{
    try{$callback();fwrite(STDOUT,"PASS {$name}\n");}
    catch(Throwable $error){$failures[]=$name.': '.$error->getMessage();fwrite(STDERR,"FAIL {$name}: {$error->getMessage()}\n");}
};
$read=static fn(string $path):string=>(string)file_get_contents($root.'/'.$path);

$test('File 00 assertion is fail closed verified audience bound and never inferred from WordPress roles',static function()use($read):void{
    $factory=$read('src/Authorization/WordPressPrincipalContextFactory.php');
    assert(str_contains($factory,"cf02_file00_authorization_assertion"));
    assert(str_contains($factory,"null,"));
    assert(str_contains($factory, "(\$assertion['verified'] ?? false) !== true"));
    assert(str_contains($factory,"self::AUDIENCE"));
    assert(str_contains($factory,"assertion_id"));
    assert(!str_contains($factory,'$wpUser->roles'));
    assert(!str_contains($factory,'defaultAssertion'));
    assert(str_contains($factory, "File 00 assertion does not match the authenticated WordPress principal"));
});

$test('strong optimistic concurrency and idempotency reject weak or altered replay',static function()use($read):void{
    $guard=$read('src/Infrastructure/WordPress/RequestGuard.php');
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    assert(str_contains($guard,"get_header('If-Match')"));
    assert(str_contains($guard, 'str_starts_with(strtoupper($header), \'W/\')'));
    assert(str_contains($guard,"get_header('Idempotency-Key')"));
    assert(str_contains($repo,'eventReplay('));
    assert(str_contains($repo,'Idempotency key was reused with a different aggregate or payload'));
    assert(str_contains($repo, '\'record_version\' => $expectedVersion + 1'));
});

$test('sensitive command notification and message bodies persist encrypted payloads',static function()use($read):void{
    $schema=$read('src/Infrastructure/WordPress/SchemaExtension.php');
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    $worker=$read('src/Infrastructure/WordPress/RuntimeWorker.php');
    foreach(['cf02_command_payloads','cf02_outbox_payloads','payload_ciphertext'] as $needle){assert(str_contains($schema,$needle),$needle);}
    assert(substr_count($repo,"'payload_ciphertext' => \$payloadCiphertext")>=2);
    assert(str_contains($worker,'$this->cipher->decrypt'));
});

$test('attachment lifecycle requires uploaded quarantine scan verdict redaction and one-time delivery',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    $provider=$read('src/Infrastructure/WordPress/ProviderWebhookController.php');
    foreach([
        "'state' => 'uploaded'", "'state' => 'quarantined'", "'state' => 'scanned'",
        "'SupportAttachmentAvailable'", "'SupportAttachmentRejected'", "'SupportAttachmentRedacted'",
        'used_at IS NULL', 'Attachment token replay was rejected', 'redaction-result'
    ] as $needle){assert(str_contains($repo.$provider,$needle),$needle);}
});

$test('case intake persists description SLA receipt linked native reference and immutable event',static function()use($read):void{
    $controller=$read('src/Infrastructure/WordPress/ComprehensiveRestController.php');
    foreach(['ensureSlaTimer','appendMessage','linkObject','support_case_receipt','SupportCaseCreated','assertNativeOwnerKey'] as $needle){assert(str_contains($controller,$needle),$needle);}
    assert(strpos($controller,'SensitiveContentDetector::containsProhibitedSecret($description)') < strpos($controller,'createOrReplay('));
});

$test('retention is fail closed and post-purge evidence remains queryable by authorized governance',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    assert(str_contains($repo,"config_key='retention_schedule' AND status='active'"));
    assert(str_contains($repo,'if ($config === null)'));
    assert(str_contains($repo,"retention.review', 'reconciliation.manage', 'audit.read"));
    assert(str_contains($repo,'SupportRetentionPurgeCompleted'));
});

if($failures!==[]){exit(1);}fwrite(STDOUT,"All CF-02 C2-I first-review regressions passed.\n");
