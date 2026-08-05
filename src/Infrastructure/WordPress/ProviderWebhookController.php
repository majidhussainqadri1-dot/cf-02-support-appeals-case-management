<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Sabri\CF02\Authorization\PrincipalContext;
use Sabri\CF02\Configuration\CategoryRoutingPolicy;
use Sabri\CF02\Contracts\SupportContractCatalog;
use Sabri\CF02\Domain\SupportCaseId;
use Sabri\CF02\Governance\ServiceEqualityPolicy;
use Sabri\CF02\Security\DataCipher;
use Sabri\CF02\Security\SensitiveContentDetector;
use Throwable;

/** Signed inbound/provider adapter endpoints. No provider can become a truth owner. */
final class ProviderWebhookController
{
    private IntakeRepository $intake;

    public function __construct(
        CaseRepository $cases,
        private readonly OperationsRepository $operations,
        private readonly DataCipher $cipher
    ) {
        $this->intake = new IntakeRepository($cases);
    }

    public function registerRoutes(): void
    {
        foreach (['cf02/v1', 'api/support/v1'] as $namespace) {
            register_rest_route($namespace, '/inbound/(?P<channel>email|system|webhook)', [
                'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => [$this, 'inbound'],
            ]);
            register_rest_route($namespace, '/attachments/(?P<id>CF02-ATT-[A-F0-9]{20})/scan-result', [
                'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => [$this, 'scanResult'],
            ]);
            register_rest_route($namespace, '/attachments/(?P<id>CF02-ATT-[A-F0-9]{20})/redaction-result', [
                'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => [$this, 'redactionResult'],
            ]);
            register_rest_route($namespace, '/native/(?P<owner>[A-Za-z0-9_-]{2,64})/results', [
                'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => [$this, 'nativeResult'],
            ]);
            register_rest_route($namespace, '/attachments/consume', [
                'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => [$this, 'consumeAttachment'],
            ]);
        }
    }

    public function inbound(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $this->verifySignature($request, 'inbound');
            $payload = $this->payload($request);
            $sourceOwner = ApiInput::safeReference($payload['source_owner'] ?? '', 'Source owner', 128);
            $externalId = ApiInput::safeReference($payload['external_event_id'] ?? '', 'External event ID', 191);
            $senderRef = ApiInput::safeReference($payload['sender_ref'] ?? '', 'Sender reference', 191);
            $senderTrust = sanitize_key((string) ($payload['sender_trust'] ?? 'unverified'));
            if ($sourceOwner === '' || $externalId === '' || $senderRef === ''
                || !in_array($senderTrust, ['verified','unverified','system'], true)) {
                throw new RuntimeException('Inbound adapter metadata is incomplete.');
            }
            $body = trim((string) ($payload['body'] ?? ''));
            if ($body === '' || strlen($body) > 20000 || SensitiveContentDetector::containsProhibitedSecret($body)) {
                throw new RuntimeException('Inbound message is empty, too long or contains prohibited secrets.');
            }
            $hash = hash('sha256', $request->get_body());
            $existing = $this->operations->inboundReceipt($sourceOwner, $externalId);
            if ($existing !== null) {
                if (!hash_equals((string) $existing['payload_hash'], $hash)) {
                    throw new RuntimeException('Inbound replay identifier was reused with changed content.');
                }
                return ['receipt' => $existing, 'replayed' => true];
            }

            $requesterRef = 'external:' . hash('sha256', $sourceOwner . "\0" . $senderRef);
            $caseId = isset($payload['case_id']) && is_string($payload['case_id']) && $payload['case_id'] !== ''
                ? SupportCaseId::fromString($payload['case_id'])
                : null;
            if ($caseId === null) {
                $category = CategoryRoutingPolicy::normalize(sanitize_key((string) ($payload['category'] ?? 'technical')));
                SupportContractCatalog::assertCategory($category);
                $impact = sanitize_key((string) ($payload['impact'] ?? 'single_action'));
                $urgency = sanitize_key((string) ($payload['urgency'] ?? 'normal'));
                $caseResult = $this->intake->createOrReplay(
                    $requesterRef,
                    'provider_' . substr(hash('sha256', $sourceOwner . "\0" . $externalId), 0, 48),
                    [
                        'category' => $category,
                        'priority' => ServiceEqualityPolicy::requesterPriority($impact, $urgency),
                        'severity' => 'normal',
                        'locale' => ApiInput::locale($payload['locale'] ?? null),
                        'subject' => ApiInput::safeSingleLine($payload['subject'] ?? 'Inbound support request', 'Inbound subject', 191, true),
                    ],
                    $this->now()
                );
                $caseId = SupportCaseId::fromString((string) $caseResult['case']['case_uuid']);
            }

            $context = $this->providerContext($sourceOwner, $requesterRef);
            $messageId = $this->operations->appendMessage(
                $caseId, $context, 'requester', (string) $request['channel'],
                $this->cipher->encrypt($body), hash('sha256', $body),
                'provider-message-' . substr(hash('sha256', $sourceOwner . "\0" . $externalId), 0, 40),
                'signed_inbound_adapter', $this->now()
            );
            $receiptId = $this->operations->recordInboundReceipt(
                $sourceOwner, $externalId, (string) $request['channel'], $senderRef, $senderTrust,
                hash('sha256', (string) $request->get_header('X-CF02-Signature')), $hash,
                $caseId->value(), $this->now()
            );
            return ['receipt_id' => $receiptId, 'case_id' => $caseId->value(), 'message_id' => $messageId, 'replayed' => false];
        }, 202);
    }

    public function scanResult(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $this->verifySignature($request, 'attachment_scan');
            $payload = $this->payload($request);
            return $this->operations->recordAttachmentScan(
                (string) $request['id'],
                sanitize_text_field((string) ($payload['provider_ref'] ?? '')),
                sanitize_key((string) ($payload['verdict'] ?? '')),
                strtolower(sanitize_text_field((string) ($payload['sha256'] ?? ''))),
                sanitize_text_field((string) ($payload['scanner_version'] ?? '')),
                'scan-' . substr(hash('sha256', $request->get_body()), 0, 48),
                $this->now()
            );
        });
    }

    public function redactionResult(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $this->verifySignature($request, 'attachment_redaction');
            $payload = $this->payload($request);
            $redactedRef = sanitize_text_field((string) ($payload['redacted_ref'] ?? ''));
            if ($redactedRef === '' || strlen($redactedRef) > 191) {
                throw new RuntimeException('Redacted provider reference is invalid.');
            }
            return $this->operations->recordAttachmentRedaction(
                (string) $request['id'], $redactedRef,
                'redaction-' . substr(hash('sha256', $request->get_body()), 0, 48), $this->now()
            );
        });
    }

    public function nativeResult(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $this->verifySignature($request, 'native_result');
            $payload = $this->payload($request);
            $commandId = sanitize_text_field((string) ($payload['command_id'] ?? ''));
            $owner = sanitize_key((string) $request['owner']);
            \Sabri\CF02\Contracts\SupportContractCatalog::assertNativeOwnerKey($owner);
            $command = $this->operations->command($commandId);
            if ($command === null || !hash_equals((string) $command['native_owner'], $owner)) {
                throw new RuntimeException('Native command was not found.');
            }
            $status = sanitize_key((string) ($payload['status'] ?? ''));
            $outcomeRef = sanitize_text_field((string) ($payload['outcome_ref'] ?? ''));
            $nativeVersion = (int) ($payload['native_version'] ?? 0);
            if (!in_array($status, ['succeeded','failed','outcome_uncertain'], true)
                || $nativeVersion < (int) $command['expected_native_version']
                || ($status === 'succeeded' && $outcomeRef === '')) {
                throw new RuntimeException('Native result is invalid or stale.');
            }
            $state = $status;
            $evidencePayload = [
                'command_ref' => $commandId, 'status' => $status,
                'outcome_ref' => $outcomeRef, 'native_version' => $nativeVersion,
            ];
            if (in_array((string) $command['state'], ['succeeded','failed'], true)) {
                $existing = $this->operations->nativeResultEvidence($commandId);
                if ($existing === $evidencePayload && hash_equals((string) $command['state'], $state)
                    && hash_equals((string) ($command['outcome_ref'] ?? ''), $outcomeRef)) {
                    return ['command_id' => $commandId, 'state' => $state, 'reconciled' => $state === 'succeeded', 'replayed' => true];
                }
                throw new RuntimeException('A terminal native command result cannot be changed.');
            }
            $this->operations->updateCommandResult(
                $commandId, $state, $outcomeRef === '' ? null : $outcomeRef,
                (int) $command['attempts'] + 1, $state === 'outcome_uncertain' ? $this->now()->modify('+5 minutes') : null, $this->now()
            );
            $context = $this->providerContext($owner);
            $this->operations->appendEvent(
                'case', (string) $command['case_uuid'], 'SupportNativeCommandResultRecorded', $context,
                'native_result_reconciliation', 'native-result-' . substr(hash('sha256', $request->get_body()), 0, 40),
                $evidencePayload, (int) $command['record_version'] + 1, $this->now()
            );
            return ['command_id' => $commandId, 'state' => $state, 'reconciled' => $state === 'succeeded', 'replayed' => false];
        });
    }

    public function consumeAttachment(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
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
            $url=(string)($delivery['delivery_url']??'');$expires=isset($delivery['expires_at'])?strtotime((string)$delivery['expires_at']):false;
            $disposition=(string)($delivery['content_disposition']??'attachment');
            if($url===''||!str_starts_with(strtolower($url),'https://')||wp_http_validate_url($url)===false
                ||$expires===false||$expires<=time()||$expires>time()+300||strlen($disposition)>191||strpbrk($disposition,"\r\n")!==false){
                throw new RuntimeException('Secure attachment provider returned an unsafe delivery grant.');
            }
            return ['delivery'=>['authorized'=>true,'expires_at'=>(string)$delivery['expires_at'],'delivery_url'=>$url,'content_disposition'=>$disposition]];
        });
    }

    private function verifySignature(\WP_REST_Request $request, string $purpose): void
    {
        $timestamp = trim((string) $request->get_header('X-CF02-Timestamp'));
        $signature = strtolower(trim((string) $request->get_header('X-CF02-Signature')));
        $keyId = trim((string) $request->get_header('X-CF02-Key-Id'));
        if (preg_match('/^[0-9]{10}$/', $timestamp) !== 1 || preg_match('/^[a-f0-9]{64}$/', $signature) !== 1
            || preg_match('/^[A-Za-z0-9._:-]{3,64}$/', $keyId) !== 1) {
            throw new RuntimeException('Provider signature metadata is invalid.');
        }
        if (abs(time() - (int) $timestamp) > 300) {
            throw new RuntimeException('Provider signature timestamp is outside the replay window.');
        }
        /** @var mixed $key */
        $key = apply_filters('cf02_provider_signing_key', '', $keyId, $purpose);
        if (!is_string($key) || strlen($key) < 32) {
            throw new RuntimeException('Provider signing key is unavailable.');
        }
        $envelope = implode("\n", [$purpose, $keyId, strtoupper($request->get_method()), $request->get_route(), $timestamp, $request->get_body()]);
        $expected = hash_hmac('sha256', $envelope, $key);
        if (!hash_equals($expected, $signature)) {
            throw new RuntimeException('Provider signature verification failed.');
        }
    }

    /** @return array<string,mixed> */
    private function payload(\WP_REST_Request $request): array
    {
        $raw=$request->get_body();
        if(strlen($raw)<2||strlen($raw)>262144||!str_starts_with(ltrim($raw),'{')){
            throw new RuntimeException('A bounded JSON object request body is required.');
        }
        $payload = $request->get_json_params();
        if (!is_array($payload) || array_is_list($payload)) {
            throw new RuntimeException('A bounded JSON object request body is required.');
        }
        return $payload;
    }

    private function providerContext(string $owner, ?string $actorReference = null): PrincipalContext
    {
        $now = $this->now();
        return new PrincipalContext(
            $actorReference ?? ('provider:' . sanitize_key($owner)), 1, ['system'], ['system.worker','case.assigned.read'], [],
            false, $now, 'File 00', '1.0.0-provider', $now, $now->modify('+5 minutes')
        );
    }

    private function now(): DateTimeImmutable { return new DateTimeImmutable('now', new DateTimeZone('UTC')); }

    /** @param callable():array<string,mixed> $callback */
    private function run(callable $callback, int $status = 200): \WP_REST_Response|\WP_Error
    {
        try {
            return new \WP_REST_Response($callback(), $status, ['Cache-Control' => 'no-store']);
        } catch (Throwable $error) {
            $trace=RequestGuard::traceId();
            do_action('cf02_provider_request_failed',['trace_id'=>$trace,'error_class'=>$error::class,'error'=>$error]);
            return new \WP_Error('cf02_provider_request_rejected', __('The signed provider request was rejected.', 'cf-02-support-appeals-case-management'), [
                'status' => 422, 'trace_id' => $trace,
            ]);
        }
    }
}
