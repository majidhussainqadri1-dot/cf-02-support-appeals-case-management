from pathlib import Path

repo=Path('src/Infrastructure/WordPress/OperationsRepository.php')
s=repo.read_text()
old="""        if ((string) $row['state'] !== 'available') {\n            throw new RuntimeException('Attachment is not eligible for redaction.');\n        }\n        $version = (int) $row['record_version'];\n        $this->transaction(function () use ($attachmentId, $redactedRef, $idempotencyKey, $payload, $system, $version, $at): void {\n            $updated = $this->wpdb->update($this->tables['attachments'], [\n                'state' => 'redacted', 'redacted_ref' => $redactedRef, 'record_version' => $version + 1,\n            ], ['attachment_uuid' => $attachmentId, 'state' => 'available', 'record_version' => $version]);\n"""
new="""        $fromState = (string) $row['state'];\n        RuntimeWorkflowPolicy::assertAttachment($fromState, 'redacted');\n        $version = (int) $row['record_version'];\n        $this->transaction(function () use ($attachmentId, $redactedRef, $idempotencyKey, $payload, $system, $version, $fromState, $at): void {\n            $updated = $this->wpdb->update($this->tables['attachments'], [\n                'state' => 'redacted', 'redacted_ref' => $redactedRef, 'record_version' => $version + 1,\n            ], ['attachment_uuid' => $attachmentId, 'state' => $fromState, 'record_version' => $version]);\n"""
if old not in s:
    raise SystemExit('expected redaction block not found')
s=s.replace(old,new,1)
needle="use RuntimeException;\n"
if 'use Sabri\\CF02\\Application\\RuntimeWorkflowPolicy;' not in s:
    s=s.replace(needle,needle+'use Sabri\\CF02\\Application\\RuntimeWorkflowPolicy;\n',1)
repo.write_text(s)

test=Path('tests/c2n-r24-r33.php')
t=test.read_text()
needle="    $runtime=$read('src/Application/RuntimeWorkflowPolicy.php');\n"
insert="    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');\n    assert(str_contains($repo, 'RuntimeWorkflowPolicy::assertAttachment($fromState, \\'redacted\\')'));\n    assert(str_contains($repo, \\'\\'state\\' => $fromState\\'));\n"
if insert not in t:
    t=t.replace(needle,insert+needle,1)
test.write_text(t)
