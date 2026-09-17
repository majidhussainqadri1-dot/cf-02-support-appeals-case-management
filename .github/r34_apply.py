from pathlib import Path

ROOT = Path('.')

def read(path: str) -> str:
    return (ROOT / path).read_text()

def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content)

def replace_once(text: str, old: str, new: str, label: str) -> str:
    if old not in text:
        raise SystemExit(f'R34 expected block missing: {label}')
    return text.replace(old, new, 1)

repo_path = 'src/Infrastructure/WordPress/OperationsRepository.php'
repo = read(repo_path)

# R34-01: exact concurrent message replay must recover rather than emit a false failure.
old = '''        $this->transaction(function () use ($messageId, $caseId, $context, $visibility, $channel, $ciphertext, $contentHash, $idempotencyKey, $purpose, $at): void {
            $ok = $this->wpdb->insert($this->tables['messages'], [
                'message_uuid' => $messageId,
                'case_uuid' => $caseId->value(),
                'author_ref' => $context->actorReference(),
                'visibility' => $visibility,
                'channel' => $channel,
                'body_ciphertext' => $ciphertext,
                'body_hash' => $contentHash,
                'record_version' => 1,
                'created_at' => $this->mysqlTime($at),
            ]);
            if ($ok !== 1) {
                throw new RuntimeException('Message persistence failed.');
            }
            $event = $visibility === 'requester' && str_starts_with($context->actorReference(), 'user:')
                ? 'SupportUserReplied' : 'SupportAgentReplied';
            $this->appendEvent('case', $caseId->value(), $event, $context, $purpose, $idempotencyKey, [
                'message_ref' => $messageId, 'visibility' => $visibility, 'channel' => $channel,
            ], (int) $this->value($this->wpdb->prepare(
                "SELECT record_version FROM {$this->tables['cases']} WHERE case_uuid=%s",
                $caseId->value()
            )), $at);
        });
        return $messageId;
'''
new = '''        try {
            $this->transaction(function () use ($messageId, $caseId, $context, $visibility, $channel, $ciphertext, $contentHash, $idempotencyKey, $purpose, $at): void {
                $ok = $this->wpdb->insert($this->tables['messages'], [
                    'message_uuid' => $messageId,
                    'case_uuid' => $caseId->value(),
                    'author_ref' => $context->actorReference(),
                    'visibility' => $visibility,
                    'channel' => $channel,
                    'body_ciphertext' => $ciphertext,
                    'body_hash' => $contentHash,
                    'record_version' => 1,
                    'created_at' => $this->mysqlTime($at),
                ]);
                if ($ok !== 1) {
                    throw new RuntimeException('Message persistence failed.');
                }
                $event = $visibility === 'requester' && str_starts_with($context->actorReference(), 'user:')
                    ? 'SupportUserReplied' : 'SupportAgentReplied';
                $this->appendEvent('case', $caseId->value(), $event, $context, $purpose, $idempotencyKey, [
                    'message_ref' => $messageId, 'visibility' => $visibility, 'channel' => $channel,
                ], (int) $this->value($this->wpdb->prepare(
                    "SELECT record_version FROM {$this->tables['cases']} WHERE case_uuid=%s",
                    $caseId->value()
                )), $at);
            });
        } catch (RuntimeException $error) {
            $replayed = $this->row($this->wpdb->prepare(
                "SELECT message_uuid,case_uuid,author_ref,visibility,channel,body_hash FROM {$this->tables['messages']} WHERE message_uuid=%s LIMIT 1",
                $messageId
            ));
            if ($replayed !== null
                && hash_equals((string) $replayed['case_uuid'], $caseId->value())
                && hash_equals((string) $replayed['author_ref'], $context->actorReference())
                && hash_equals((string) $replayed['visibility'], $visibility)
                && hash_equals((string) $replayed['channel'], $channel)
                && hash_equals((string) $replayed['body_hash'], $contentHash)) {
                return $messageId;
            }
            throw $error;
        }
        return $messageId;
'''
repo = replace_once(repo, old, new, 'appendMessage concurrent replay')

# R34-02: inbound receipt unique-key races recover only when the durable receipt is semantically identical.
old = '''        if ($ok !== 1) {
            throw new RuntimeException('Inbound receipt persistence failed.');
        }
        return $id;
    }

    /** @param array<string,mixed> $payload */
    public function appendEvent(
'''
new = '''        if ($ok !== 1) {
            $existing = $this->inboundReceipt($sourceOwner, $externalEventId);
            if ($existing !== null
                && hash_equals((string) $existing['receipt_uuid'], $id)
                && hash_equals((string) $existing['channel'], $channel)
                && hash_equals((string) $existing['sender_ref'], $senderRef)
                && hash_equals((string) $existing['sender_trust'], $senderTrust)
                && hash_equals((string) $existing['payload_hash'], $payloadHash)
                && hash_equals((string) ($existing['case_uuid'] ?? ''), (string) ($caseId ?? ''))) {
                return $id;
            }
            throw new RuntimeException('Inbound receipt persistence failed or conflicted.');
        }
        return $id;
    }

    /** @param array<string,mixed> $payload */
    public function appendEvent(
'''
repo = replace_once(repo, old, new, 'inbound receipt concurrent replay')

# R34-03: terminal attachment scan evidence is immutable, not merely the coarse final state/provider.
old = '''        if (in_array((string) $existing['state'], ['available','rejected'], true)) {
            if (!hash_equals((string) $existing['state'], $state)
                || !hash_equals((string) ($existing['provider_ref'] ?? ''), $providerRef)) {
                throw new RuntimeException('Attachment scan replay differs from the recorded result.');
            }
            $this->appendEvent('attachment', $attachmentId, $state === 'rejected' ? 'SupportAttachmentRejected' : 'SupportAttachmentAvailable', $system, 'attachment_scan', $idempotencyKey, [
                'verdict' => $verdict, 'scanner_version' => $scannerVersion, 'provider_ref' => $providerRef,
            ], (int) $existing['record_version'], $at);
            return $existing;
        }
'''
new = '''        if (in_array((string) $existing['state'], ['available','rejected'], true)) {
            if (!hash_equals((string) $existing['state'], $state)
                || !hash_equals((string) ($existing['provider_ref'] ?? ''), $providerRef)) {
                throw new RuntimeException('Attachment scan replay differs from the recorded result.');
            }
            $eventType = $state === 'rejected' ? 'SupportAttachmentRejected' : 'SupportAttachmentAvailable';
            $recorded = $this->row($this->wpdb->prepare(
                "SELECT payload_json FROM {$this->tables['events']} WHERE aggregate_type='attachment' AND aggregate_ref=%s AND event_type=%s ORDER BY id DESC LIMIT 1",
                $attachmentId, $eventType
            ));
            $recordedPayload = $recorded === null ? null : json_decode((string) $recorded['payload_json'], true);
            $expectedPayload = ['verdict' => $verdict, 'scanner_version' => $scannerVersion, 'provider_ref' => $providerRef];
            if (!is_array($recordedPayload) || $recordedPayload !== $expectedPayload) {
                throw new RuntimeException('Attachment scan replay differs from the recorded evidence.');
            }
            return $existing;
        }
'''
repo = replace_once(repo, old, new, 'terminal scan evidence immutability')

# R34-04: if a duplicate scan races the winning transaction, recover the exact committed evidence.
old = '''        $this->transaction(function () use ($attachmentId, $providerRef, $state, $verdict, $scannerVersion, $idempotencyKey, $system, $at, $baseVersion): void {
            $scanned = $this->wpdb->update($this->tables['attachments'], [
                'provider_ref' => $providerRef, 'state' => 'scanned', 'record_version' => $baseVersion + 1,
            ], ['attachment_uuid' => $attachmentId, 'state' => 'quarantined', 'record_version' => $baseVersion]);
            if ($scanned !== 1) {
                throw new RuntimeException('Attachment scan transition conflicted.');
            }
            $finalized = $this->wpdb->update($this->tables['attachments'], [
                'state' => $state, 'record_version' => $baseVersion + 2,
                'expires_at' => $state === 'available' ? $this->mysqlTime($at->modify('+7 days')) : $this->mysqlTime($at->modify('+1 day')),
            ], ['attachment_uuid' => $attachmentId, 'state' => 'scanned', 'record_version' => $baseVersion + 1]);
            if ($finalized !== 1) {
                throw new RuntimeException('Attachment verdict transition conflicted.');
            }
            $this->appendEvent('attachment', $attachmentId, $state === 'rejected' ? 'SupportAttachmentRejected' : 'SupportAttachmentAvailable', $system, 'attachment_scan', $idempotencyKey, [
                'verdict' => $verdict, 'scanner_version' => $scannerVersion, 'provider_ref' => $providerRef,
            ], $baseVersion + 2, $at);
        });
        return $this->row($this->wpdb->prepare("SELECT * FROM {$this->tables['attachments']} WHERE attachment_uuid=%s", $attachmentId))
            ?? throw new RuntimeException('Attachment could not be reloaded.');
'''
new = '''        try {
            $this->transaction(function () use ($attachmentId, $providerRef, $state, $verdict, $scannerVersion, $idempotencyKey, $system, $at, $baseVersion): void {
                $scanned = $this->wpdb->update($this->tables['attachments'], [
                    'provider_ref' => $providerRef, 'state' => 'scanned', 'record_version' => $baseVersion + 1,
                ], ['attachment_uuid' => $attachmentId, 'state' => 'quarantined', 'record_version' => $baseVersion]);
                if ($scanned !== 1) {
                    throw new RuntimeException('Attachment scan transition conflicted.');
                }
                $finalized = $this->wpdb->update($this->tables['attachments'], [
                    'state' => $state, 'record_version' => $baseVersion + 2,
                    'expires_at' => $state === 'available' ? $this->mysqlTime($at->modify('+7 days')) : $this->mysqlTime($at->modify('+1 day')),
                ], ['attachment_uuid' => $attachmentId, 'state' => 'scanned', 'record_version' => $baseVersion + 1]);
                if ($finalized !== 1) {
                    throw new RuntimeException('Attachment verdict transition conflicted.');
                }
                $this->appendEvent('attachment', $attachmentId, $state === 'rejected' ? 'SupportAttachmentRejected' : 'SupportAttachmentAvailable', $system, 'attachment_scan', $idempotencyKey, [
                    'verdict' => $verdict, 'scanner_version' => $scannerVersion, 'provider_ref' => $providerRef,
                ], $baseVersion + 2, $at);
            });
        } catch (RuntimeException $error) {
            $payload = ['verdict' => $verdict, 'scanner_version' => $scannerVersion, 'provider_ref' => $providerRef];
            $eventType = $state === 'rejected' ? 'SupportAttachmentRejected' : 'SupportAttachmentAvailable';
            if ($this->eventReplay($attachmentId, $eventType, $idempotencyKey, $payload, 'attachment')) {
                $replayed = $this->row($this->wpdb->prepare("SELECT * FROM {$this->tables['attachments']} WHERE attachment_uuid=%s LIMIT 1", $attachmentId));
                if ($replayed !== null && hash_equals((string) $replayed['state'], $state)
                    && hash_equals((string) ($replayed['provider_ref'] ?? ''), $providerRef)) {
                    return $replayed;
                }
            }
            throw $error;
        }
        return $this->row($this->wpdb->prepare("SELECT * FROM {$this->tables['attachments']} WHERE attachment_uuid=%s", $attachmentId))
            ?? throw new RuntimeException('Attachment could not be reloaded.');
'''
repo = replace_once(repo, old, new, 'scan concurrent replay recovery')

write(repo_path, repo)

provider_path = 'src/Infrastructure/WordPress/ProviderWebhookController.php'
provider = read(provider_path)
old = '''        $expected = hash_hmac('sha256', $timestamp . '.' . $request->get_body(), $key);
        if (!hash_equals($expected, $signature)) {
'''
new = '''        $route = $request->get_route();
        $method = strtoupper($request->get_method());
        if (!is_string($route) || $route === '' || !in_array($method, ['POST'], true)) {
            throw new RuntimeException('Provider signing target is invalid.');
        }
        $signingTarget = $timestamp . "\\n" . $purpose . "\\n" . $method . "\\n" . $route . "\\n" . $request->get_body();
        $expected = hash_hmac('sha256', $signingTarget, $key);
        if (!hash_equals($expected, $signature)) {
'''
provider = replace_once(provider, old, new, 'signature target binding')
write(provider_path, provider)

# Add a cumulative R34 regression without changing the production review flow.
test_path = 'tests/c2n-r24-r33.php'
test = read(test_path)
marker = '\nif($failures!==[]){'
if marker not in test:
    raise SystemExit('R34 test insertion marker missing')
addition = r'''
$test(34,'signed provider adapters bind route identity and exact concurrent replays recover safely',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    $provider=$read('src/Infrastructure/WordPress/ProviderWebhookController.php');
    assert(str_contains($provider,'$request->get_route()'));
    assert(str_contains($provider,'$request->get_method()'));
    assert(str_contains($provider,'$timestamp . "\\n" . $purpose . "\\n" . $method . "\\n" . $route'));
    assert(str_contains($repo,'Inbound receipt persistence failed or conflicted.'));
    assert(str_contains($repo,'Attachment scan replay differs from the recorded evidence.'));
    assert(str_contains($repo,"eventReplay($attachmentId, $eventType, $idempotencyKey, $payload, 'attachment')"));
    $messageStart=strpos($repo,'public function appendMessage(');
    $messageEnd=strpos($repo,'public function linkObject(',$messageStart);
    $messageBlock=substr($repo,$messageStart,$messageEnd-$messageStart);
    assert(str_contains($messageBlock,'catch (RuntimeException $error)'));
    assert(substr_count($messageBlock,'body_hash')>=3);
});
'''
if "$test(34,'signed provider adapters bind route identity" not in test:
    test = test.replace(marker, addition + marker, 1)
test = test.replace('CF-02 R24-R33 regression register passed through R33.', 'CF-02 cumulative regression register passed through R34.', 1)
write(test_path, test)

print('R34 corrections materialized')
