from pathlib import Path


def replace(path: str, old: str, new: str, label: str) -> None:
    p=Path(path); s=p.read_text()
    if old not in s: raise SystemExit(f'R32 missing block: {label}')
    p.write_text(s.replace(old,new,1))

repo='src/Infrastructure/WordPress/OperationsRepository.php'
old='''    /** @return array<string,mixed> */
    public function consumeAttachmentToken(string $token, DateTimeImmutable $at): array
    {
        $hash = hash('sha256', $token);
        $row = $this->row($this->wpdb->prepare(
            "SELECT t.*,a.provider_ref,a.redacted_ref,a.state,a.case_uuid FROM {$this->tables['tokens']} t
             JOIN {$this->tables['attachments']} a ON a.attachment_uuid=t.attachment_uuid
             WHERE t.token_hash=%s AND t.used_at IS NULL AND t.expires_at>UTC_TIMESTAMP(6)
             AND a.expires_at IS NOT NULL AND a.expires_at>UTC_TIMESTAMP(6) LIMIT 1",
            $hash
        ));
        if ($row === null || !in_array((string) $row['state'], ['available','redacted'], true)) {
            throw new RuntimeException('Attachment token is invalid or expired.');
        }
        $updated = $this->wpdb->update($this->tables['tokens'], ['used_at' => $this->mysqlTime($at)], ['token_hash' => $hash, 'used_at' => null]);
        if ($updated !== 1) {
            throw new RuntimeException('Attachment token replay was rejected.');
        }
        return $row;
    }
'''
new='''    /** @return array<string,mixed> */
    public function inspectAttachmentToken(string $token, DateTimeImmutable $at): array
    {
        $hash = hash('sha256', $token);
        $row = $this->row($this->wpdb->prepare(
            "SELECT t.*,a.provider_ref,a.redacted_ref,a.state,a.case_uuid FROM {$this->tables['tokens']} t
             JOIN {$this->tables['attachments']} a ON a.attachment_uuid=t.attachment_uuid
             WHERE t.token_hash=%s AND t.used_at IS NULL AND t.expires_at>UTC_TIMESTAMP(6)
             AND a.expires_at IS NOT NULL AND a.expires_at>UTC_TIMESTAMP(6) LIMIT 1",
            $hash
        ));
        if ($row === null || !in_array((string) $row['state'], ['available','redacted'], true)) {
            throw new RuntimeException('Attachment token is invalid or expired.');
        }
        return $row;
    }

    /** @return array<string,mixed> */
    public function consumeAttachmentToken(string $token, DateTimeImmutable $at): array
    {
        $row = $this->inspectAttachmentToken($token, $at);
        $hash = hash('sha256', $token);
        $updated = $this->wpdb->update($this->tables['tokens'], ['used_at' => $this->mysqlTime($at)], ['token_hash' => $hash, 'used_at' => null]);
        if ($updated !== 1) {
            throw new RuntimeException('Attachment token replay was rejected.');
        }
        return $row;
    }
'''
replace(repo,old,new,'attachment token repository split')

controller='src/Infrastructure/WordPress/ProviderWebhookController.php'
old='''    public function consumeAttachment(\\WP_REST_Request $request): \\WP_REST_Response|\\WP_Error
    {
        return $this->run(function () use ($request): array {
            $token = trim((string) $request->get_param('token'));
            if (preg_match('/^[A-Za-z0-9_-]{40,80}$/', $token) !== 1) {
                throw new RuntimeException('Attachment token is invalid or expired.');
            }
            $evidence = $this->operations->consumeAttachmentToken($token, $this->now());
            /** @var mixed $delivery */
            $delivery = apply_filters('cf02_attachment_secure_delivery', null, [
                'attachment_ref' => $evidence['attachment_uuid'],
                'provider_ref' => $evidence['state'] === 'redacted' ? $evidence['redacted_ref'] : $evidence['provider_ref'],
                'purpose' => $evidence['purpose'],
                'actor_ref' => $evidence['actor_ref'],
            ]);
            if (!is_array($delivery) || ($delivery['authorized'] ?? false) !== true) {
                throw new RuntimeException('Secure attachment provider is unavailable.');
            }
            return ['delivery' => array_intersect_key($delivery, array_flip(['authorized','expires_at','delivery_url','content_disposition']))];
        });
    }
'''
new='''    public function consumeAttachment(\\WP_REST_Request $request): \\WP_REST_Response|\\WP_Error
    {
        return $this->run(function () use ($request): array {
            $token = trim((string) $request->get_param('token'));
            if (preg_match('/^[A-Za-z0-9_-]{40,80}$/', $token) !== 1) {
                throw new RuntimeException('Attachment token is invalid or expired.');
            }
            $leaseId = hash('sha256', $token);
            if (!$this->operations->acquireWorkerLease('attachment_token', $leaseId)) {
                throw new RuntimeException('Attachment token is already being consumed.');
            }
            try {
                $evidence = $this->operations->inspectAttachmentToken($token, $this->now());
                /** @var mixed $delivery */
                $delivery = apply_filters('cf02_attachment_secure_delivery', null, [
                    'attachment_ref' => $evidence['attachment_uuid'],
                    'provider_ref' => $evidence['state'] === 'redacted' ? $evidence['redacted_ref'] : $evidence['provider_ref'],
                    'purpose' => $evidence['purpose'],
                    'actor_ref' => $evidence['actor_ref'],
                ]);
                if (!is_array($delivery) || ($delivery['authorized'] ?? false) !== true) {
                    throw new RuntimeException('Secure attachment provider is unavailable.');
                }
                // Consume only after the provider has produced an authorized delivery response.
                $this->operations->consumeAttachmentToken($token, $this->now());
                return ['delivery' => array_intersect_key($delivery, array_flip(['authorized','expires_at','delivery_url','content_disposition']))];
            } finally {
                $this->operations->releaseWorkerLease('attachment_token', $leaseId);
            }
        });
    }
'''
replace(controller,old,new,'attachment delivery controller')

p=Path('tests/c2n-r24-r33.php'); t=p.read_text()
final='''if($failures!==[]){fwrite(STDERR,implode("\\n",$failures)."\\n");exit(1);}fwrite(STDOUT,"CF-02 R24-R33 regression register passed through R31.\\n");
'''
addition='''$test(32,'attachment bearer token is consumed only after authorized secure delivery under a token lease',static function()use($read):void{
    $repo=$read('src/Infrastructure/WordPress/OperationsRepository.php');
    $provider=$read('src/Infrastructure/WordPress/ProviderWebhookController.php');
    assert(str_contains($repo,'public function inspectAttachmentToken'));
    assert(str_contains($provider,"acquireWorkerLease('attachment_token'"));
    assert(str_contains($provider,'inspectAttachmentToken($token'));
    assert(strpos($provider,"(\$delivery['authorized'] ?? false) !== true") < strpos($provider,'consumeAttachmentToken($token'));
    assert(str_contains($provider,"releaseWorkerLease('attachment_token'"));
});

'''
if final not in t: raise SystemExit('R32 final marker missing')
t=t.replace(final,addition+'''if($failures!==[]){fwrite(STDERR,implode("\\n",$failures)."\\n");exit(1);}fwrite(STDOUT,"CF-02 R24-R33 regression register passed through R32.\\n");
''',1)
p.write_text(t)
print('R32 corrections materialized')
