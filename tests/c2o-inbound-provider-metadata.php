<?php

declare(strict_types=1);

/** Executable signed-inbound identity boundary/DB-width regression suite. */
require_once dirname(__DIR__) . '/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__) . '/src');

use Sabri\CF02\Infrastructure\WordPress\InboundProviderMetadata;

$failed = [];
$count = 0;
$test = static function(string $label, callable $body) use (&$count, &$failed): void {
    ++$count;
    try {
        if ($body() !== true) { throw new RuntimeException('Check returned false'); }
        echo sprintf("PASS %02d %s\n", $count, $label);
    } catch (Throwable $e) {
        $failed[] = $label;
        fwrite(STDERR, sprintf("FAIL %02d %s: %s\n", $count, $label, $e->getMessage()));
    }
};
$valid = [
    'source_owner'=>'provider_1',
    'external_event_id'=>'evt:0001/abc@example.com',
    'sender_ref'=>'agent+lab@example.org',
    'sender_trust'=>'verified',
];
$reject = static function(array $candidate): bool {
    try { InboundProviderMetadata::fromPayload($candidate); }
    catch (RuntimeException) { return true; }
    return false;
};
$test('valid provider replay fields retain exact signed bytes',
    static fn():bool=>InboundProviderMetadata::fromPayload($valid)===$valid);
$test('unknown trust defaults to unverified', static function() use($valid):bool{
    unset($valid['sender_trust']);
    return (InboundProviderMetadata::fromPayload($valid)['sender_trust']??null)==='unverified';
});
foreach (['source_owner'=>64,'external_event_id'=>191,'sender_ref'=>191] as $field=>$width) {
    $test("$field permits exact schema-width bytes",static function()use($valid,$field,$width):bool{
        $v=$valid;$v[$field]=str_repeat('X',$width);
        return InboundProviderMetadata::fromPayload($v)[$field]===$v[$field];
    });
    $test("$field rejects overlong identity before persistence",static function()use($valid,$field,$width,$reject):bool{
        $v=$valid;$v[$field]=str_repeat('X',$width+1);
        return $reject($v);
    });
    $test("$field rejects empty identity",static function()use($valid,$field,$reject):bool{
        $v=$valid;$v[$field]='';return $reject($v);
    });
    $test("$field rejects padded aliases",static function()use($valid,$field,$reject):bool{
        $v=$valid;$v[$field]=' '.$v[$field].' ';return $reject($v);
    });
    $test("$field rejects control characters",static function()use($valid,$field,$reject):bool{
        $v=$valid;$v[$field].="\n";return $reject($v);
    });
    $test("$field rejects malformed UTF-8",static function()use($valid,$field,$reject):bool{
        $v=$valid;$v[$field]="\xC3\x28";return $reject($v);
    });
    $test("$field rejects non-string type",static function()use($valid,$field,$reject):bool{
        $v=$valid;$v[$field]=['tampered'];return $reject($v);
    });
}
foreach (['sender_trust','source_owner','external_event_id','sender_ref'] as $field) {
    $test("$field rejects absent mandatory metadata where applicable",static function()use($valid,$field,$reject):bool{
        $v=$valid;unset($v[$field]);
        return $field==='sender_trust'
            ? (InboundProviderMetadata::fromPayload($v)['sender_trust']??null)==='unverified'
            : $reject($v);
    });
}
foreach (['System','trusted','',1,null,' verified'] as $trust) {
    $test('Reject forged trust value type/spelling: '.get_debug_type($trust).':'.(string)$trust,
        static function()use($valid,$trust,$reject):bool{$v=$valid;$v['sender_trust']=$trust;return $reject($v);});
}
$test('Database source fields retain exact documented columns',static function():bool{
    $schema=(string)file_get_contents(dirname(__DIR__).'/src/Infrastructure/WordPress/SchemaExtension.php');
    return str_contains($schema,'source_owner varchar(64) NOT NULL')
        && str_contains($schema,'external_event_id varchar(191) NOT NULL')
        && str_contains($schema,'sender_ref varchar(191) NOT NULL');
});
$test('Signed inbound route uses strict metadata policy',static function():bool{
    $src=(string)file_get_contents(dirname(__DIR__).'/src/Infrastructure/WordPress/ProviderWebhookController.php');
    return str_contains($src,'InboundProviderMetadata::fromPayload($payload)')
        && !str_contains($src, "sanitize_text_field((string) ($"."payload['external_event_id']");
});
printf("CF-02 signed metadata: %d/%d pass; %d fail.\n",$count-count($failed),$count,count($failed));
exit($failed===[]?0:1);
