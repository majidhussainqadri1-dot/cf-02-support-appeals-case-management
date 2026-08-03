<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use DateTimeImmutable;
use Sabri\CF02\Domain\SupportCaseId;
use Sabri\CF02\Security\DataCipher;
use Sabri\CF02\Security\SensitiveContentDetector;
use Throwable;

final class RestController
{
    private IntakeRepository $intake;

    public function __construct(private readonly CaseRepository $repository, private readonly DataCipher $cipher)
    {
        $this->intake = new IntakeRepository($repository);
    }

    public function registerRoutes(): void
    {
        register_rest_route('cf02/v1', '/health', [
            'methods' => 'GET',
            'permission_callback' => static fn (): bool => current_user_can('manage_options'),
            'callback' => fn (): \WP_REST_Response => new \WP_REST_Response([
                'runtime_version' => CF02_VERSION,
                'schema_version' => Installer::schemaVersion(),
                'status' => 'ready',
            ], 200),
        ]);

        register_rest_route('cf02/v1', '/cases', [
            [
                'methods' => 'GET',
                'permission_callback' => [$this, 'authenticatedPermission'],
                'callback' => [$this, 'listCases'],
                'args' => [
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                    'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                ],
            ],
            [
                'methods' => 'POST',
                'permission_callback' => [$this, 'authenticatedPermission'],
                'callback' => [$this, 'createCase'],
                'args' => [
                    'category' => ['type' => 'string', 'required' => true],
                    'priority' => ['type' => 'string', 'required' => true],
                    'severity' => ['type' => 'string', 'required' => true],
                    'queue' => ['type' => 'string', 'required' => true],
                    'locale' => ['type' => 'string', 'required' => true],
                    'subject' => ['type' => 'string', 'required' => true],
                    'idempotency_key' => ['type' => 'string', 'required' => true],
                ],
            ],
        ]);

        register_rest_route('cf02/v1', '/cases/(?P<id>CF02-[0-9A-F-]{36})', [
            'methods' => 'GET',
            'permission_callback' => [$this, 'authenticatedPermission'],
            'callback' => [$this, 'getCase'],
        ]);

        register_rest_route('cf02/v1', '/cases/(?P<id>CF02-[0-9A-F-]{36})/messages', [
            'methods' => 'POST',
            'permission_callback' => [$this, 'authenticatedPermission'],
            'callback' => [$this, 'addMessage'],
            'args' => [
                'body' => ['type' => 'string', 'required' => true],
                'channel' => ['type' => 'string', 'required' => true],
                'idempotency_key' => ['type' => 'string', 'required' => true],
            ],
        ]);
    }

    public function authenticatedPermission(): bool|\WP_Error
    {
        if (!is_user_logged_in() || !current_user_can('read')) {
            return new \WP_Error('cf02_not_found', __('Resource not found.', 'cf-02-support-appeals-case-management'), ['status' => 404]);
        }
        return true;
    }

    public function listCases(\WP_REST_Request $request): \WP_REST_Response
    {
        $rows = $this->repository->listRequesterCases($this->requesterReference(), (int) $request['limit'], (int) $request['offset']);
        return new \WP_REST_Response(['items' => $rows], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function createCase(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            $payload = [
                'category' => sanitize_key((string) $request['category']),
                'priority' => strtoupper(sanitize_text_field((string) $request['priority'])),
                'severity' => sanitize_key((string) $request['severity']),
                'queue' => sanitize_key((string) $request['queue']),
                'locale' => sanitize_text_field((string) $request['locale']),
                'subject' => sanitize_text_field((string) $request['subject']),
            ];
            $idempotencyKey = sanitize_text_field((string) $request['idempotency_key']);
            if ($payload['category'] === '' || $payload['severity'] === '' || $payload['queue'] === '' || $payload['subject'] === ''
                || !in_array($payload['priority'], ['P1', 'P2', 'P3', 'P4'], true)
                || preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $payload['locale']) !== 1
                || preg_match('/^[A-Za-z0-9_-]{16,128}$/', $idempotencyKey) !== 1) {
                return new \WP_Error('cf02_invalid_case', __('The case request is invalid.', 'cf-02-support-appeals-case-management'), ['status' => 422]);
            }
            if (SensitiveContentDetector::containsProhibitedSecret($payload['subject'])) {
                return new \WP_Error('cf02_sensitive_content', __('Remove passwords, OTPs, card data or private keys.', 'cf-02-support-appeals-case-management'), ['status' => 422]);
            }
            $result = $this->intake->createOrReplay(
                $this->requesterReference(),
                $idempotencyKey,
                $payload,
                new DateTimeImmutable('now', new \DateTimeZone('UTC'))
            );
            return new \WP_REST_Response(
                ['case' => $result['case'], 'replayed' => $result['replayed']],
                $result['replayed'] ? 200 : 201,
                ['Cache-Control' => 'private, no-store']
            );
        } catch (Throwable) {
            return new \WP_Error('cf02_case_failed', __('The case could not be created. Retry with the same idempotency key or contact support.', 'cf-02-support-appeals-case-management'), ['status' => 500, 'trace_id' => 'tr_' . bin2hex(random_bytes(16))]);
        }
    }

    public function getCase(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            $case = $this->repository->getCase(SupportCaseId::fromString((string) $request['id']), $this->requesterReference());
            if ($case === null) {
                return new \WP_Error('cf02_not_found', __('Resource not found.', 'cf-02-support-appeals-case-management'), ['status' => 404]);
            }
            return new \WP_REST_Response($case, 200, ['Cache-Control' => 'private, no-store']);
        } catch (Throwable) {
            return new \WP_Error('cf02_not_found', __('Resource not found.', 'cf-02-support-appeals-case-management'), ['status' => 404]);
        }
    }

    public function addMessage(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            $body = trim((string) $request['body']);
            $channel = sanitize_key((string) $request['channel']);
            $idempotencyKey = sanitize_text_field((string) $request['idempotency_key']);
            if ($body === '' || strlen($body) > 20_000 || !in_array($channel, ['web', 'email', 'chat'], true)
                || preg_match('/^[A-Za-z0-9_-]{16,64}$/', $idempotencyKey) !== 1) {
                return new \WP_Error('cf02_invalid_message', __('The message request is invalid.', 'cf-02-support-appeals-case-management'), ['status' => 422]);
            }
            if (SensitiveContentDetector::containsProhibitedSecret($body)) {
                return new \WP_Error('cf02_sensitive_content', __('Remove passwords, OTPs, card data or private keys.', 'cf-02-support-appeals-case-management'), ['status' => 422]);
            }
            $messageId = $this->repository->addRequesterMessage(
                SupportCaseId::fromString((string) $request['id']),
                $this->requesterReference(),
                $channel,
                $this->cipher->encrypt($body),
                hash('sha256', $body),
                $idempotencyKey,
                new DateTimeImmutable('now', new \DateTimeZone('UTC'))
            );
            return new \WP_REST_Response(['message_id' => $messageId, 'status' => 'accepted'], 202, ['Cache-Control' => 'private, no-store']);
        } catch (Throwable) {
            return new \WP_Error('cf02_message_failed', __('The message could not be accepted.', 'cf-02-support-appeals-case-management'), ['status' => 500, 'trace_id' => 'tr_' . bin2hex(random_bytes(16))]);
        }
    }

    private function requesterReference(): string
    {
        return 'user:' . (string) get_current_user_id();
    }
}
