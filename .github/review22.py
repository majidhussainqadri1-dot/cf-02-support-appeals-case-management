from pathlib import Path

p = Path('src/Infrastructure/WordPress/OperationsRepository.php')
s = p.read_text()
old = """        if ($existing !== null) {
            if (!hash_equals((string) $existing['payload_hash'], $payloadHash)
                || !hash_equals((string) $existing['recipient_ref'], $recipientRef)
                || !hash_equals((string) $existing['channel'], $channel)) {
                throw new RuntimeException('Delivery idempotency collision.');
            }
            return (string) $existing['message_uuid'];
        }
"""
new = """        if ($existing !== null) {
            if (!hash_equals((string) $existing['case_uuid'], $caseId->value())
                || !hash_equals((string) $existing['template_key'], $templateKey)
                || !hash_equals((string) $existing['payload_hash'], $payloadHash)
                || !hash_equals((string) $existing['recipient_ref'], $recipientRef)
                || !hash_equals((string) $existing['channel'], $channel)) {
                throw new RuntimeException('Delivery idempotency collision.');
            }
            return (string) $existing['message_uuid'];
        }
"""
assert old in s
s = s.replace(old, new, 1)
p.write_text(s)

t = Path('tests/c2n-fresh40.php')
x = t.read_text()
marker = "\nif($failures!==[]){fwrite(STDERR,implode(\"\\n\",$failures).\"\\n\");exit(1);}fwrite(STDOUT,\"CF-02 fresh 40-round regression register passed through round 21.\\n\");"
assert marker in x
add = r'''

$test(22,'delivery idempotency binds case and template as well as recipient channel and payload',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    assert(str_contains($repo, "existing['case_uuid']"));
    assert(str_contains($repo, "existing['template_key']"));
    assert(str_contains($repo, "Delivery idempotency collision."));
});

if($failures!==[]){fwrite(STDERR,implode("\n",$failures)."\n");exit(1);}fwrite(STDOUT,"CF-02 fresh 40-round regression register passed through round 22.\n");'''
x=x.replace(marker,add)
t.write_text(x)
