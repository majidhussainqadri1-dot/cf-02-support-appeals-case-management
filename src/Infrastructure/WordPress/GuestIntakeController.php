<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use DateTimeImmutable;
use RuntimeException;
use Sabri\CF02\Authorization\WordPressPrincipalContextFactory;
use Sabri\CF02\Configuration\CategoryRoutingPolicy;
use Sabri\CF02\Domain\SupportCaseId;
use Sabri\CF02\Governance\ServiceEqualityPolicy;
use Sabri\CF02\Intake\GuestContinuationToken;
use Sabri\CF02\Intake\GuestIntakePolicy;
use Sabri\CF02\Safety\EmergencyRunbookRegistry;
use Sabri\CF02\Security\DataCipher;
use Throwable;

/** Safe anonymous pre-intake followed by authenticated case creation. */
final class GuestIntakeController
{
    private IntakeRepository $intake;
    private WordPressPrincipalContextFactory $contexts;

    public function __construct(
        CaseRepository $cases,
        private readonly OperationsRepository $operations,
        private readonly DataCipher $cipher,
        private readonly GuestContinuationToken $tokens
    ) {
        $this->intake = new IntakeRepository($cases);
        $this->contexts = new WordPressPrincipalContextFactory();
    }

    public function registerRoutes(): void
    {
        foreach (['cf02/v1', 'api/support/v1'] as $namespace) {
            register_rest_route($namespace, '/guest/intake', [
                'methods' => 'POST',
                'permission_callback' => '__return_true',
                'callback' => [$this, 'issueContinuation'],
            ]);
            register_rest_route($namespace, '/guest/continue', [
                'methods' => 'POST',
                'permission_callback' => [$this, 'authenticatedPermission'],
                'callback' => [$this, 'continueCase'],
            ]);
        }
    }

    public function authenticatedPermission(): bool|\WP_Error
    {
        try {
            $now = new DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $context = $this->contexts->current($now);
            if (!$context->validAt($now) || !$context->hasCapability('case.create')) {
                throw new RuntimeException('Authenticated case-create capability is required.');
            }
            return true;
        } catch (Throwable) {
            return new \WP_Error('cf02_guest_step_up_required', __('Sign in to continue this support request.', 'cf-02-support-appeals-case-management'), ['status' => 401]);
        }
    }

    public function issueContinuation(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            if (!$this->consumeRateLimit()) {
                return new \WP_Error('cf02_guest_rate_limited', __('Too many support-intake attempts. Please try again later.', 'cf-02-support-appeals-case-management'), ['status' => 429]);
            }
            $input = [
                'category' => (string) $request->get_param('category'),
                'subject' => (string) $request->get_param('subject'),
                'description' => (string) $request->get_param('description'),
                'impact' => (string) ($request->get_param('impact') ?: 'single_action'),
                'urgency' => (string) ($request->get_param('urgency') ?: 'normal'),
                'locale' => (string) ($request->get_param('locale') ?: 'ur-PK'),
            ];
            $safe = GuestIntakePolicy::normalize($input);
            $runbookType = EmergencyRunbookRegistry::classifyText($safe['subject'] . "\n" . $safe['description']);
            if ($runbookType !== null) {
                $runbook = EmergencyRunbookRegistry::forType($runbookType);
                return new \WP_REST_Response([
                    'status' => 'diverted',
                    'case_created' => false,
                    'runbook_type' => $runbookType,
                    'owner_domain' => $runbook['owner'],
                    'safe_mode' => $runbook['mode'],
                    'ordinary_support_sla_applies' => false,
                    'requires_immediate_direction' => true,
                ], 422);
            }
            $now = new DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $token = $this->tokens->issue($safe, $now, 600);
            return new \WP_REST_Response([
                'status' => 'step_up_required',
                'case_created' => false,
                'continuation_token' => $token,
                'expires_at' => $now->modify('+10 minutes')->format(DATE_ATOM),
                'authentication_required_for_disclosure' => true,
                'guest_categories' => GuestIntakePolicy::categories(),
            ], 202);
        } catch (Throwable $error) {
            return new \WP_Error('cf02_guest_intake_rejected', __('Guest support intake could not be accepted safely.', 'cf-02-support-appeals-case-management'), [
                'status' => 400,
                'trace_id' => 'CF02-GUEST-' . strtoupper(substr(hash('sha256', $error::class . '|' . microtime(true)), 0, 16)),
            ]);
        }
    }

    public function continueCase(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            $now = new DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $context = $this->contexts->current($now);
            if (!$context->validAt($now) || !$context->hasCapability('case.create')) {
                throw new RuntimeException('Authenticated case-create capability is required.');
            }
            $token = trim((string) $request->get_param('continuation_token'));
            $verified = $this->tokens->verify($token, $now);
            $safe = GuestIntakePolicy::normalize($verified['intake']);
            $priority = ServiceEqualityPolicy::requesterPriority($safe['impact'], $safe['urgency']);
            $queue = CategoryRoutingPolicy::queueFor($safe['category']);
            $idempotencyKey = 'guest-cont-' . substr(hash('sha256', $token), 0, 40);
            $payload = [
                'category' => $safe['category'],
                'priority' => $priority,
                'severity' => 'normal',
                'queue' => $queue,
                'locale' => $safe['locale'],
                'subject' => $safe['subject'],
                'impact' => $safe['impact'],
                'urgency' => $safe['urgency'],
            ];
            $result = $this->intake->createOrReplay($context->actorReference(), $idempotencyKey, $payload, $now);
            $caseId = SupportCaseId::fromString((string) $result['case']['case_uuid']);
            $this->operations->ensureSlaTimer($caseId, $priority, $now);
            $this->operations->appendEvent('case', $caseId->value(), 'SupportCaseCreated', $context, 'guest_step_up', $idempotencyKey, [
                'category' => $safe['category'],
                'priority' => $priority,
                'queue' => $queue,
                'guest_continuation' => true,
                'token_id_hash' => hash('sha256', (string) $verified['token_id']),
            ], 1, $now);
            if ($safe['description'] !== '') {
                $this->operations->appendMessage(
                    $caseId,
                    $context,
                    'requester',
                    'web',
                    $this->cipher->encrypt($safe['description']),
                    hash('sha256', $safe['description']),
                    $idempotencyKey . ':description',
                    'guest_step_up',
                    $now
                );
            }
            $receipt = [
                'case_id' => $caseId->value(),
                'status' => 'new',
                'next_step' => 'triage',
                'received_at' => $now->format(DATE_ATOM),
            ];
            $this->operations->enqueueDelivery(
                $caseId,
                $context->actorReference(),
                'in_app',
                'support_case_receipt',
                $receipt,
                $this->cipher->encrypt(wp_json_encode($receipt, JSON_THROW_ON_ERROR)),
                $idempotencyKey . ':receipt',
                $now
            );
            return new \WP_REST_Response([
                'case' => $result['case'],
                'replayed' => $result['replayed'],
                'continuation_completed' => true,
                'receipt_queued' => true,
            ], $result['replayed'] ? 200 : 201);
        } catch (Throwable $error) {
            return new \WP_Error('cf02_guest_continuation_rejected', __('The support continuation is invalid, expired, or not authorized.', 'cf-02-support-appeals-case-management'), [
                'status' => 400,
                'trace_id' => 'CF02-CONT-' . strtoupper(substr(hash('sha256', $error::class . '|' . microtime(true)), 0, 16)),
            ]);
        }
    }

    private function consumeRateLimit(): bool
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $bucket = (string) floor(time() / 900);
        $key = 'cf02_guest_' . substr(hash_hmac('sha256', $ip . '|' . $bucket, wp_salt('nonce')), 0, 40);
        $count = (int) get_transient($key);
        if ($count >= 10) {
            return false;
        }
        set_transient($key, $count + 1, 900);
        return true;
    }
}
