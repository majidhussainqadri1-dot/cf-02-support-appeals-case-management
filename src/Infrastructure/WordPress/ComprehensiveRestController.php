<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Sabri\CF02\Application\RuntimeWorkflowPolicy;
use Sabri\CF02\Configuration\CategoryRoutingPolicy;
use Sabri\CF02\Authorization\PrincipalContext;
use Sabri\CF02\Authorization\WordPressPrincipalContextFactory;
use Sabri\CF02\Contracts\SupportContractCatalog;
use Sabri\CF02\Domain\SupportCaseId;
use Sabri\CF02\Governance\ServiceEqualityPolicy;
use Sabri\CF02\Security\DataCipher;
use Sabri\CF02\Security\SensitiveContentDetector;
use Throwable;

/** Complete plan-v1.0 REST command/query surface with a compatibility namespace. */
final class ComprehensiveRestController
{
    private IntakeRepository $intake;
    private WordPressPrincipalContextFactory $contexts;

    public function __construct(
        private readonly CaseRepository $cases,
        private readonly OperationsRepository $operations,
        private readonly DataCipher $cipher
    ) {
        $this->intake = new IntakeRepository($cases);
        $this->contexts = new WordPressPrincipalContextFactory();
    }

    public function registerRoutes(): void
    {
        foreach (['cf02/v1', 'api/support/v1'] as $namespace) {
            $this->registerNamespace($namespace);
        }
    }

    private function registerNamespace(string $namespace): void
    {
        $auth = [$this, 'authenticatedPermission'];
        $routes = [
            ['/health', 'GET', 'health'],
            ['/contracts', 'GET', 'contracts'],
            ['/cases', 'GET', 'myCases'],
            ['/cases', 'POST', 'createCase'],
            ['/cases/(?P<id>CF02-[0-9A-F-]{36})', 'GET', 'getCase'],
            ['/cases/(?P<id>CF02-[0-9A-F-]{36})/reply-options', 'GET', 'replyOptions'],
            ['/cases/(?P<id>CF02-[0-9A-F-]{36})/messages', 'POST', 'addRequesterMessage'],
            ['/cases/(?P<id>CF02-[0-9A-F-]{36})/attachments', 'POST', 'createAttachment'],
            ['/cases/(?P<id>CF02-[0-9A-F-]{36})/attachments/(?P<attachment>CF02-ATT-[A-F0-9]{20})/token', 'POST', 'attachmentToken'],
            ['/cases/(?P<id>CF02-[0-9A-F-]{36})/withdraw', 'POST', 'withdrawCase'],
            ['/cases/(?P<id>CF02-[0-9A-F-]{36})/reopen', 'POST', 'reopenOwnCase'],
            ['/cases/(?P<id>CF02-[0-9A-F-]{36})/feedback', 'POST', 'submitFeedback'],
            ['/appeals', 'POST', 'submitAppeal'],
            ['/appeals/(?P<id>CF02-APL-[A-F0-9]{20})', 'GET', 'getAppeal'],
            ['/staff/queue', 'GET', 'assignedQueue'],
            ['/staff/cases/search', 'GET', 'searchCases'],
            ['/staff/cases/(?P<id>CF02-[0-9A-F-]{36})', 'GET', 'workbench'],
            ['/staff/cases/(?P<id>CF02-[0-9A-F-]{36})/triage', 'POST', 'triageCase'],
            ['/staff/cases/(?P<id>CF02-[0-9A-F-]{36})/priority', 'POST', 'setPriority'],
            ['/staff/cases/(?P<id>CF02-[0-9A-F-]{36})/assign', 'POST', 'assignCase'],
            ['/staff/cases/(?P<id>CF02-[0-9A-F-]{36})/transfer', 'POST', 'transferCase'],
            ['/staff/cases/(?P<id>CF02-[0-9A-F-]{36})/escalate', 'POST', 'escalateCase'],
            ['/staff/cases/(?P<id>CF02-[0-9A-F-]{36})/incident-link', 'POST', 'linkMajorIncident'],
            ['/staff/cases/(?P<id>CF02-[0-9A-F-]{36})/waiting', 'POST', 'waitCase'],
            ['/staff/cases/(?P<id>CF02-[0-9A-F-]{36})/messages', 'POST', 'addAgentMessage'],
            ['/staff/cases/(?P<id>CF02-[0-9A-F-]{36})/notes', 'POST', 'addInternalNote'],
            ['/staff/cases/(?P<id>CF02-[0-9A-F-]{36})/notes/(?P<message>CF02-MSG-[A-F0-9]{20})', 'POST', 'editInternalNote'],
            ['/staff/cases/(?P<id>CF02-[0-9A-F-]{36})/tasks', 'POST', 'createTask'],
            ['/staff/tasks/(?P<id>CF02-TASK-[A-F0-9]{20})/complete', 'POST', 'completeTask'],
            ['/staff/cases/(?P<id>CF02-[0-9A-F-]{36})/request-user-info', 'POST', 'requestUserInfo'],
            ['/staff/cases/(?P<id>CF02-[0-9A-F-]{36})/request-provider-action', 'POST', 'requestProviderAction'],
            ['/staff/cases/(?P<id>CF02-[0-9A-F-]{36})/resolve', 'POST', 'resolveCase'],
            ['/staff/cases/(?P<id>CF02-[0-9A-F-]{36})/close', 'POST', 'closeCase'],
            ['/staff/cases/(?P<id>CF02-[0-9A-F-]{36})/reopen', 'POST', 'reopenResolvedCase'],
            ['/staff/cases/(?P<id>CF02-[0-9A-F-]{36})/holds', 'POST', 'applyHold'],
            ['/staff/cases/(?P<id>CF02-[0-9A-F-]{36})/holds/(?P<hold>CF02-HOLD-[A-F0-9]{20})/release', 'POST', 'releaseHold'],
            ['/staff/cases/merge', 'POST', 'mergeCases'],
            ['/staff/cases/(?P<id>CF02-[0-9A-F-]{36})/split', 'POST', 'splitCase'],
            ['/staff/cases/(?P<id>CF02-[0-9A-F-]{36})/quality', 'POST', 'recordQuality'],
            ['/staff/appeals', 'GET', 'appealQueue'],
            ['/staff/appeals/(?P<id>CF02-APL-[A-F0-9]{20})/eligibility', 'POST', 'appealEligibility'],
            ['/staff/appeals/(?P<id>CF02-APL-[A-F0-9]{20})/conflict-check', 'GET', 'appealConflictCheck'],
            ['/staff/appeals/(?P<id>CF02-APL-[A-F0-9]{20})/reviewer', 'POST', 'assignAppealReviewer'],
            ['/staff/appeals/(?P<id>CF02-APL-[A-F0-9]{20})/native-action', 'POST', 'requestAppealNativeAction'],
            ['/staff/appeals/(?P<id>CF02-APL-[A-F0-9]{20})/decision', 'POST', 'recordAppealDecision'],
            ['/staff/appeals/(?P<id>CF02-APL-[A-F0-9]{20})/implementation', 'POST', 'confirmAppealImplementation'],
            ['/staff/appeals/(?P<id>CF02-APL-[A-F0-9]{20})/remand', 'POST', 'remandAppeal'],
            ['/staff/appeals/(?P<id>CF02-APL-[A-F0-9]{20})/close', 'POST', 'closeAppeal'],
            ['/staff/configuration', 'POST', 'stageConfiguration'],
            ['/staff/configuration/(?P<key>[a-z][a-z0-9_]{2,63})/(?P<version>[1-9][0-9]*)/activate', 'POST', 'activateConfiguration'],
            ['/staff/configuration/(?P<key>[a-z][a-z0-9_]{2,63})/(?P<version>[1-9][0-9]*)/rollback', 'POST', 'rollbackConfiguration'],
            ['/staff/sla/at-risk', 'GET', 'slaAtRisk'],
            ['/staff/commands/(?P<id>CF02-CMD-[A-F0-9]{20})', 'GET', 'nativeCommandStatus'],
            ['/staff/cases/(?P<id>CF02-[0-9A-F-]{36})/links', 'GET', 'linkedDomainProjection'],
            ['/staff/cases/(?P<id>CF02-[0-9A-F-]{36})/export-status', 'GET', 'exportStatus'],
            ['/staff/retention/(?P<id>CF02-[0-9A-F-]{36})/reconciliation', 'GET', 'purgeReconciliation'],
            ['/staff/metrics/backlog', 'GET', 'backlogHealth'],
            ['/staff/metrics/sla', 'GET', 'slaMetrics'],
            ['/staff/metrics/reopen', 'GET', 'reopenMetrics'],
            ['/staff/metrics/quality', 'GET', 'qualitySample'],
            ['/staff/retention/due', 'GET', 'retentionDue'],
        ];

        foreach ($routes as [$route, $method, $callback]) {
            register_rest_route($namespace, $route, [
                'methods' => $method,
                'permission_callback' => $auth,
                'callback' => [$this, $callback],
            ]);
        }
    }

    public function authenticatedPermission(): bool|\WP_Error
    {
        try {
            $context = $this->contexts->current($this->now());
            if (!$context->validAt($this->now())) {
                throw new RuntimeException('Authorization assertion is expired or suspended.');
            }
            return true;
        } catch (Throwable) {
            return new \WP_Error('cf02_not_found', __('Resource not found.', 'cf-02-support-appeals-case-management'), ['status' => 404]);
        }
    }

    public function health(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function (): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'release.evidence.read', 'case.own.read');
            return [
                'runtime_version' => CF02_VERSION,
                'schema_version' => Installer::schemaVersion(),
                'plan_version' => CF02_PLAN_VERSION,
                'contract_version' => SupportContractCatalog::CONTRACT_VERSION,
                'status' => 'ready',
                'fail_closed' => true,
            ];
        });
    }

    public function contracts(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function (): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'release.evidence.read', 'case.own.read');
            return [
                'version' => SupportContractCatalog::CONTRACT_VERSION,
                'commands' => SupportContractCatalog::commands(),
                'queries' => SupportContractCatalog::queries(),
                'events' => SupportContractCatalog::events(),
                'categories' => SupportContractCatalog::categories(),
                'native_owners' => SupportContractCatalog::nativeOwners(),
            ];
        });
    }

    public function myCases(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return new \WP_Error('cf02_cursor_runtime_required', __('The canonical keyset-pagination service is unavailable.', 'cf-02-support-appeals-case-management'), ['status' => 503, 'trace_id' => RequestGuard::traceId()]);
    }

    public function createCase(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'case.create');
            $category = CategoryRoutingPolicy::normalize(sanitize_key((string) $request->get_param('category')));
            SupportContractCatalog::assertCategory($category);
            $subject = sanitize_text_field((string) $request->get_param('subject'));
            if ($subject === '' || strlen($subject) > 191 || SensitiveContentDetector::containsProhibitedSecret($subject)) {
                throw new RuntimeException('The case subject is missing or contains prohibited secret data.');
            }
            $description = trim((string) $request->get_param('description'));
            if (strlen($description) > 20000 || ($description !== '' && SensitiveContentDetector::containsProhibitedSecret($description))) {
                throw new RuntimeException('The case description is too long or contains prohibited secret data.');
            }
            $safety = strtolower($subject . ' ' . (string) $request->get_param('description'));
            if (preg_match('/\b(suicide|kill myself|heart attack|unconscious|severe bleeding|emergency|خودکشی|دل کا دورہ|بے ہوش|شدید خون)\b/u', $safety) === 1) {
                throw new RuntimeException('Immediate danger is not an ordinary support ticket. Use approved local emergency services now.');
            }
            $locale = ApiInput::locale($request->get_param('locale'));
            $impact = sanitize_key((string) $request->get_param('impact'));
            $urgency = sanitize_key((string) $request->get_param('urgency'));
            if (!in_array($impact, ['', 'single_action', 'account_blocked', 'many_users'], true)
                || !in_array($urgency, ['', 'normal', 'time_sensitive'], true)) {
                throw new RuntimeException('Impact or urgency is invalid.');
            }
            // Requesters describe impact/urgency; they never grant themselves P1/P2 authority.
            $priority = ServiceEqualityPolicy::requesterPriority($impact, $urgency);
            $queue = CategoryRoutingPolicy::queueFor($category);
            $payload = [
                'category' => $category,
                'subcategory' => sanitize_key((string) $request->get_param('subcategory')),
                'priority' => $priority,
                'severity' => 'normal',
                'queue' => $queue,
                'locale' => $locale,
                'subject' => $subject,
                'impact' => $impact,
                'urgency' => $urgency,
                'accessibility' => sanitize_text_field((string) $request->get_param('accessibility')),
                'diagnostics_consented' => ApiInput::boolean($request->get_param('diagnostics_consented'), 'Diagnostics consent', false),
            ];
            $key = RequestGuard::idempotencyKey($request);
            $result = $this->intake->createOrReplay($context->actorReference(), $key, $payload, $this->now());
            $caseId = SupportCaseId::fromString((string) $result['case']['case_uuid']);
            $this->operations->ensureSlaTimer($caseId, $priority, $this->now());
            $this->operations->appendEvent('case', $caseId->value(), 'SupportCaseCreated', $context, 'support_intake', $key, [
                'category' => $category, 'priority' => $priority, 'queue' => $queue,
                'diagnostics_consented' => $payload['diagnostics_consented'],
            ], 1, $this->now());
            if ($description !== '') {
                $this->operations->appendMessage(
                    $caseId, $context, 'requester', 'web', $this->cipher->encrypt($description), hash('sha256', $description),
                    $key . ':description', 'support_intake', $this->now()
                );
            }
            $object = $request->get_param('affected_object');
            if (is_array($object) && !empty($object['owner']) && !empty($object['type']) && !empty($object['ref']) && !empty($object['version'])) {
                SupportContractCatalog::assertNativeOwnerKey(sanitize_key((string) $object['owner']));
                $safeProjection = isset($object['safe_projection']) && is_array($object['safe_projection']) ? $object['safe_projection'] : [];
                $this->operations->linkObject(
                    $caseId, $context, sanitize_key((string) $object['owner']), sanitize_key((string) $object['type']),
                    sanitize_text_field((string) $object['ref']), sanitize_text_field((string) $object['version']),
                    strtoupper(sanitize_text_field((string) ($object['privacy_class'] ?? 'C2'))), $safeProjection,
                    $key . ':linked-object:' . substr(hash('sha256', (string) $object['owner'] . "\0" . (string) $object['ref']), 0, 24), $this->now()
                );
            }
            $receiptPayload = [
                'case_id' => $caseId->value(), 'category' => $category, 'received_at' => $this->now()->format(DATE_ATOM),
                'status' => 'new', 'next_step' => 'triage', 'sla_range' => $priority === 'P1' ? 'urgent' : 'standard',
                'emergency_boundary' => true,
            ];
            $this->operations->enqueueDelivery(
                $caseId, $context->actorReference(), 'in_app', 'support_case_receipt', $receiptPayload,
                $this->cipher->encrypt(wp_json_encode($receiptPayload, JSON_THROW_ON_ERROR)), $key . ':receipt', $this->now()
            );
            return ['case' => $result['case'], 'replayed' => $result['replayed'], 'receipt_queued' => true];
        }, 201);
    }

    public function getCase(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $projection = $this->operations->caseProjection($this->caseId($request), $this->context());
            return $this->decryptProjection($projection);
        });
    }

    public function replyOptions(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $case = $this->operations->caseForActor($this->caseId($request), $this->context(), false);
            $state = (string) $case['state'];
            return [
                'can_reply' => !in_array($state, ['closed','withdrawn'], true),
                'can_attach' => !in_array($state, ['closed','withdrawn'], true),
                'can_reopen' => in_array($state, ['resolved','closed','withdrawn'], true),
                'allowed_channels' => ['web','email','chat'],
                'prohibited_content' => ['password','otp','private_key','full_card','unnecessary_clinical_evidence'],
            ];
        });
    }

    public function addRequesterMessage(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->message($request, 'requester', ['case.own.reply','case.represented.reply']);
    }

    public function addAgentMessage(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->message($request, 'requester', ['case.assigned.reply','case.specialist.reply']);
    }

    public function addInternalNote(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $visibility = ApiInput::boolean($request->get_param('restricted'), 'Restricted-note flag', false) ? 'restricted' : 'internal';
        return $this->message($request, $visibility, ['case.assigned.note','case.specialist.note']);
    }

    public function editInternalNote(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'case.assigned.note','case.specialist.note');
            RequestGuard::purpose($request, true);
            $body = trim((string) $request->get_param('body'));
            if ($body === '' || strlen($body) > 20000 || SensitiveContentDetector::containsProhibitedSecret($body)) {
                throw new RuntimeException('Internal note is empty, too long or contains prohibited secret data.');
            }
            return $this->operations->reviseInternalNote(
                (string) $request['message'], $this->caseId($request), $context,
                RequestGuard::expectedVersion($request), $this->cipher->encrypt($body), hash('sha256', $body), $this->now()
            );
        });
    }

    private function message(\WP_REST_Request $request, string $visibility, array $capabilities): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request, $visibility, $capabilities): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), ...$capabilities);
            $purpose = RequestGuard::purpose($request, $visibility !== 'requester' || $context->hasCapability('case.assigned.reply'));
            $body = trim((string) $request->get_param('body'));
            if ($body === '' || strlen($body) > 20000 || SensitiveContentDetector::containsProhibitedSecret($body)) {
                throw new RuntimeException('Message is empty, too long or contains prohibited secret data.');
            }
            $channel = sanitize_key((string) ($request->get_param('channel') ?: 'web'));
            $caseId = $this->caseId($request);
            $key = RequestGuard::idempotencyKey($request);
            $id = $this->operations->appendMessage(
                $caseId, $context, $visibility, $channel,
                $this->cipher->encrypt($body), hash('sha256', $body),
                $key, $purpose ?: 'case_reply', $this->now()
            );
            $this->operations->resumeSla($caseId, 'message:' . $id, $this->now());
            if ($visibility === 'requester') {
                $case = $this->operations->caseForActor($caseId, $context);
                $recipient = hash_equals((string) $case['requester_ref'], $context->actorReference())
                    ? (string) ($case['owner_ref'] ?? '') : (string) $case['requester_ref'];
                if ($recipient !== '') {
                    $delivery = ['case_id' => $caseId->value(), 'message_id' => $id, 'channel' => $channel];
                    $this->operations->enqueueDelivery(
                        $caseId, $recipient, 'in_app', 'support_case_reply', $delivery,
                        $this->cipher->encrypt(wp_json_encode($delivery, JSON_THROW_ON_ERROR)), $key . ':delivery', $this->now()
                    );
                }
            }
            return ['message_id' => $id, 'status' => 'accepted'];
        }, 202);
    }

    public function createAttachment(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'case.own.attach','case.represented.attach','case.assigned.reply','case.specialist.reply');
            if (!ApiInput::boolean($request->get_param('consented'), 'Attachment consent', false)) {
                throw new RuntimeException('Attachment consent is required.');
            }
            $purpose = RequestGuard::purpose($request, true);
            $row = $this->operations->createAttachment(
                $this->caseId($request), $context,
                sanitize_text_field((string) $request->get_param('mime_type')),
                (int) $request->get_param('size'),
                strtolower(sanitize_text_field((string) $request->get_param('sha256'))),
                $purpose,
                strtoupper(sanitize_text_field((string) ($request->get_param('privacy_class') ?: 'C3'))),
                RequestGuard::idempotencyKey($request),
                $this->now()
            );
            /** @var mixed $provider */
            $provider = apply_filters('cf02_attachment_quarantine_request', null, [
                'attachment' => $row, 'actor_ref' => $context->actorReference(), 'purpose' => $purpose,
            ]);
            $accepted = is_array($provider) && ($provider['accepted'] ?? false) === true;
            $upload = $accepted ? array_intersect_key($provider, array_flip(['provider_ref','upload_url','headers','expires_at'])) : [];
            if ($accepted) {
                $url = (string) ($upload['upload_url'] ?? '');
                $expires = isset($upload['expires_at']) ? strtotime((string) $upload['expires_at']) : false;
                if ($url === '' || !str_starts_with(strtolower($url), 'https://') || wp_http_validate_url($url) === false
                    || $expires === false || $expires <= time() || $expires > time() + 900) {
                    throw new RuntimeException('Attachment provider returned an unsafe upload session.');
                }
                $upload['headers'] = ApiInput::safeHeaderMap($upload['headers'] ?? []);
            }
            return ['attachment' => $row, 'provider_request_accepted' => $accepted, 'upload_session' => $upload];
        }, 202);
    }

    public function attachmentToken(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'case.own.read','case.represented.read','case.assigned.read','case.specialist.read');
            return $this->operations->issueAttachmentToken((string) $request['attachment'], $this->caseId($request), $context, RequestGuard::purpose($request, true), $this->now());
        });
    }

    public function withdrawCase(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->caseTransition($request, 'withdrawn', 'WithdrawCase', 'SupportCaseWithdrawn', ['case.own.withdraw'], 'case_withdrawal');
    }

    public function reopenOwnCase(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->caseTransition($request, 'reopened', 'ReopenCase', 'SupportCaseReopened', ['case.own.reopen','case.represented.reopen'], 'case_reopen');
    }

    public function submitFeedback(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'feedback.own.submit');
            $optedOut = ApiInput::boolean($request->get_param('opted_out'), 'Feedback opt-out', false);
            $comment = trim((string) $request->get_param('comment'));
            if ($comment !== '' && SensitiveContentDetector::containsProhibitedSecret($comment)) {
                throw new RuntimeException('Feedback cannot contain secret data.');
            }
            return $this->operations->addFeedback(
                $this->caseId($request), $context,
                $optedOut ? null : (int) $request->get_param('rating'),
                $optedOut || $comment === '' ? null : $this->cipher->encrypt($comment),
                $optedOut, $this->now()
            );
        }, 201);
    }

    public function submitAppeal(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'appeal.own.submit','appeal.represented.submit');
            $evidence = ApiInput::referenceList($request->get_param('evidence_refs'));
            $grounds = ApiInput::safeTextarea($request->get_param('grounds'), 10000, true);
            return $this->operations->submitAppeal(
                SupportCaseId::fromString((string) $request->get_param('case_id')),
                $context,
                sanitize_text_field((string) $request->get_param('original_decision_ref')),
                sanitize_text_field((string) $request->get_param('policy_version')),
                $evidence,
                $grounds,
                RequestGuard::idempotencyKey($request),
                $this->now()
            );
        }, 201);
    }

    public function getAppeal(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(fn (): array => $this->operations->appealProjection((string) $request['id'], $this->context()));
    }

    public function assignedQueue(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return new \WP_Error('cf02_cursor_runtime_required', __('The canonical keyset-pagination service is unavailable.', 'cf-02-support-appeals-case-management'), ['status' => 503, 'trace_id' => RequestGuard::traceId()]);
    }

    public function searchCases(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return new \WP_Error('cf02_cursor_runtime_required', __('The canonical keyset-pagination service is unavailable.', 'cf-02-support-appeals-case-management'), ['status' => 503, 'trace_id' => RequestGuard::traceId()]);
    }

    public function workbench(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::purpose($request, true);
            return $this->decryptProjection($this->operations->caseProjection($this->caseId($request), $context));
        });
    }

    public function triageCase(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'case.assigned.triage','queue.manage');
            $case = $this->operations->caseForActor($this->caseId($request), $context);
            RuntimeWorkflowPolicy::assertCase((string) $case['state'], 'triaged');
            $category = CategoryRoutingPolicy::normalize(sanitize_key((string) ($request->get_param('category') ?: $case['category'])));
            SupportContractCatalog::assertCategory($category);
            $priority = strtoupper(sanitize_text_field((string) ($request->get_param('priority') ?: $case['priority'])));
            if (!in_array($priority, ['P1','P2','P3','P4'], true)) {
                throw new RuntimeException('Priority is invalid.');
            }
            return $this->operations->mutateCase(
                $this->caseId($request), $context, RequestGuard::expectedVersion($request),
                ['state' => 'triaged', 'category' => $category, 'priority' => $priority, 'queue_key' => CategoryRoutingPolicy::queueFor($category)],
                'TriageCase', 'SupportCaseTriaged', RequestGuard::purpose($request, true),
                RequestGuard::idempotencyKey($request), ['human_override_reason' => sanitize_text_field((string) $request->get_param('override_reason'))], $this->now()
            );
        });
    }

    public function setPriority(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'case.assigned.triage','queue.manage');
            $priority = strtoupper(sanitize_text_field((string) $request->get_param('priority')));
            if (!in_array($priority, ['P1','P2','P3','P4'], true)) {
                throw new RuntimeException('Priority is invalid.');
            }
            return $this->operations->mutateCase(
                $this->caseId($request), $context, RequestGuard::expectedVersion($request), ['priority' => $priority],
                'SetPriority', 'SupportCaseTriaged', RequestGuard::purpose($request, true), RequestGuard::idempotencyKey($request),
                ['priority' => $priority, 'reason' => sanitize_text_field((string) $request->get_param('reason'))], $this->now()
            );
        });
    }

    public function assignCase(\WP_REST_Request $request): \WP_REST_Response|\WP_Error { return $this->assignment($request, false); }
    public function transferCase(\WP_REST_Request $request): \WP_REST_Response|\WP_Error { return $this->assignment($request, true); }

    private function assignment(\WP_REST_Request $request, bool $transfer): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request, $transfer): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), $transfer ? 'case.transfer' : 'case.assign');
            $scopes = $request->get_param('scopes');
            return $this->operations->assignCase(
                $this->caseId($request), $context,
                sanitize_text_field((string) $request->get_param('agent_ref')),
                sanitize_key((string) $request->get_param('queue_key')),
                sanitize_key((string) $request->get_param('role_key')),
                is_array($scopes) ? array_values(array_filter($scopes, 'is_string')) : [],
                sanitize_text_field((string) $request->get_param('reason')),
                RequestGuard::expectedVersion($request), RequestGuard::idempotencyKey($request), $this->now(), $transfer
            );
        });
    }

    public function escalateCase(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'case.escalate','sla.manage');
            $case = $this->operations->caseForActor($this->caseId($request), $context);
            $to = (string) $case['state'] === 'triaged' ? 'in_progress' : (string) $case['state'];
            return $this->operations->mutateCase(
                $this->caseId($request), $context, RequestGuard::expectedVersion($request), ['state' => $to, 'priority' => 'P1'],
                'EscalateCase', 'SupportCaseEscalated', RequestGuard::purpose($request, true), RequestGuard::idempotencyKey($request),
                ['reason' => sanitize_text_field((string) $request->get_param('reason')), 'user_update_required' => true], $this->now()
            );
        });
    }

    public function linkMajorIncident(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'case.escalate','case.sensitive.coordinate');
            $owner = sanitize_key((string) $request->get_param('native_owner'));
            SupportContractCatalog::assertNativeOwnerKey($owner);
            return $this->operations->linkMajorIncident(
                $this->caseId($request), $context,
                sanitize_text_field((string) $request->get_param('incident_ref')), $owner,
                sanitize_text_field((string) $request->get_param('public_status')),
                RequestGuard::idempotencyKey($request), $this->now()
            );
        }, 201);
    }

    public function waitCase(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'case.assigned.reply','case.specialist.reply');
            $waitingFor = sanitize_key((string) $request->get_param('waiting_for'));
            $to = $waitingFor === 'provider' ? 'waiting_provider' : 'waiting_user';
            $caseId = $this->caseId($request);
            $case = $this->operations->caseForActor($caseId, $context);
            RuntimeWorkflowPolicy::assertCase((string) $case['state'], $to);
            $key = RequestGuard::idempotencyKey($request);
            $reason = sanitize_textarea_field((string) $request->get_param('reason'));
            if ($reason === '') {
                throw new RuntimeException('A bounded waiting reason is required.');
            }
            $mutated = $this->operations->mutateCase(
                $caseId, $context, RequestGuard::expectedVersion($request), ['state' => $to],
                $waitingFor === 'provider' ? 'RequestProviderAction' : 'RequestUserInfo', 'SupportCaseWaiting',
                'sla_wait', $key, ['reason' => $reason, 'waiting_for' => $waitingFor], $this->now()
            );
            $this->operations->pauseSla($caseId, $to, 'event:' . $key, $this->now());
            return $mutated;
        });
    }

    public function createTask(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'case.assigned.task','case.specialist.task');
            $due = (string) $request->get_param('due_at');
            return $this->operations->createTask(
                $this->caseId($request), $context, sanitize_key((string) $request->get_param('task_type')),
                ($v = trim((string) $request->get_param('assignee_ref'))) === '' ? null : $v,
                ($d = trim((string) $request->get_param('dependency_ref'))) === '' ? null : $d,
                $due === '' ? null : new DateTimeImmutable($due), RequestGuard::idempotencyKey($request),
                RequestGuard::purpose($request, true), $this->now()
            );
        }, 201);
    }

    public function completeTask(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'case.assigned.task','case.specialist.task');
            return $this->operations->completeTask((string) $request['id'], $context, RequestGuard::expectedVersion($request), sanitize_text_field((string) $request->get_param('outcome_ref')), RequestGuard::idempotencyKey($request), RequestGuard::purpose($request, true), $this->now());
        });
    }

    public function requestUserInfo(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $request->set_param('waiting_for', 'user');
        return $this->waitCase($request);
    }

    public function requestProviderAction(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'native.action.request','appeal.native.request');
            RequestGuard::requireRecentAuthentication($context, $this->now());
            $payload = $request->get_json_params();
            return $this->operations->createNativeCommand(
                $this->caseId($request), $context,
                sanitize_key((string) $request->get_param('native_owner')),
                sanitize_key((string) $request->get_param('action')),
                sanitize_text_field((string) $request->get_param('object_ref')),
                (int) $request->get_param('expected_native_version'),
                is_array($payload) ? $payload : [], $this->cipher->encrypt(wp_json_encode(is_array($payload) ? $payload : [], JSON_THROW_ON_ERROR)), RequestGuard::idempotencyKey($request),
                RequestGuard::purpose($request, true), $this->now()
            );
        }, 202);
    }

    public function resolveCase(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'case.assigned.resolve','queue.manage');
            $case = $this->operations->caseForActor($this->caseId($request), $context);
            RuntimeWorkflowPolicy::assertCase((string) $case['state'], 'resolved');
            $resolutionCode = sanitize_key((string) $request->get_param('resolution_code'));
            $nativeRef = sanitize_text_field((string) $request->get_param('native_outcome_ref'));
            $instructions = sanitize_textarea_field((string) $request->get_param('user_instructions'));
            if ($resolutionCode === '' || $instructions === '' || (ApiInput::boolean($request->get_param('native_action_required'), 'Native-action-required flag', false) && $nativeRef === '')) {
                throw new RuntimeException('Resolution requires a code, user instructions and any required native outcome.');
            }
            $caseId = $this->caseId($request);
            $key = RequestGuard::idempotencyKey($request);
            $resolved = $this->operations->mutateCase(
                $caseId, $context, RequestGuard::expectedVersion($request), ['state' => 'resolved'],
                'ResolveCase', 'SupportCaseResolved', RequestGuard::purpose($request, true), $key,
                ['resolution_code' => $resolutionCode, 'native_outcome_ref' => $nativeRef, 'verified' => ApiInput::boolean($request->get_param('verified'), 'Resolution verification flag', false), 'closure_notice_sent' => ApiInput::boolean($request->get_param('closure_notice_sent'), 'Closure-notice flag', false)], $this->now()
            );
            $this->operations->markSlaStatus($caseId->value(), 'resolved', $this->now());
            $notice = ['case_id' => $caseId->value(), 'resolution_code' => $resolutionCode, 'instructions' => $instructions, 'reopen_available' => true];
            $this->operations->enqueueDelivery($caseId, (string) $resolved['requester_ref'], 'in_app', 'support_case_resolved', $notice, $this->cipher->encrypt(wp_json_encode($notice, JSON_THROW_ON_ERROR)), $key . ':notice', $this->now());
            return $resolved;
        });
    }

    public function closeCase(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'case.assigned.resolve','queue.manage');
            $case = $this->operations->caseForActor($this->caseId($request), $context);
            RuntimeWorkflowPolicy::assertCase((string) $case['state'], 'closed');
            if (!ApiInput::boolean($request->get_param('user_confirmed'), 'User-confirmation flag', false) && !ApiInput::boolean($request->get_param('eligible_auto_close_notice_sent'), 'Auto-close notice flag', false)) {
                throw new RuntimeException('Closure requires user confirmation or an eligible noticed auto-close policy.');
            }
            return $this->operations->mutateCase(
                $this->caseId($request), $context, RequestGuard::expectedVersion($request), ['state' => 'closed', 'closed_at' => $this->now()->format('Y-m-d H:i:s.u')],
                'CloseCase', 'SupportCaseClosed', RequestGuard::purpose($request, true), RequestGuard::idempotencyKey($request),
                ['user_confirmed' => ApiInput::boolean($request->get_param('user_confirmed'), 'User-confirmation flag', false)], $this->now()
            );
        });
    }

    public function reopenResolvedCase(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->caseTransition($request, 'reopened', 'ReopenResolvedCase', 'SupportCaseReopened', ['case.assigned.resolve','queue.manage'], 'case_reopen');
    }

    public function applyHold(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'hold.apply');
            RequestGuard::requireRecentAuthentication($context, $this->now());
            return $this->operations->applyHold(
                $this->caseId($request), $context, sanitize_key((string) $request->get_param('reason_code')),
                RequestGuard::approvalReference($request), new DateTimeImmutable((string) $request->get_param('review_due_at')), RequestGuard::idempotencyKey($request), $this->now()
            );
        }, 201);
    }

    public function releaseHold(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'hold.release');
            RequestGuard::requireRecentAuthentication($context, $this->now());
            RequestGuard::approvalReference($request);
            return $this->operations->releaseHold($this->caseId($request), (string) $request['hold'], $context, RequestGuard::expectedVersion($request), sanitize_text_field((string) $request->get_param('reason')), RequestGuard::idempotencyKey($request), $this->now());
        });
    }

    public function mergeCases(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'case.merge');
            return $this->operations->mergeCases(
                SupportCaseId::fromString((string) $request->get_param('source_case_id')),
                SupportCaseId::fromString((string) $request->get_param('target_case_id')),
                $context, sanitize_text_field((string) $request->get_param('reason')), RequestGuard::idempotencyKey($request), $this->now()
            );
        });
    }

    public function splitCase(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'case.split');
            return $this->operations->splitMergedCase($this->caseId($request), $context, sanitize_text_field((string) $request->get_param('reversal_ref')), RequestGuard::idempotencyKey($request), $this->now());
        });
    }

    public function recordQuality(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'quality.manage');
            $scores = $request->get_param('scores');
            $findings = $request->get_param('findings');
            return $this->operations->recordQuality(
                $this->caseId($request), $context, sanitize_key((string) $request->get_param('sample_basis')),
                is_array($scores) ? $scores : [], is_array($findings) ? array_values(array_filter($findings, 'is_string')) : [],
                ApiInput::boolean($request->get_param('identity_suppressed'), 'Identity-suppression flag', false), $this->now()
            );
        }, 201);
    }

    public function appealQueue(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return new \WP_Error('cf02_cursor_runtime_required', __('The canonical keyset-pagination service is unavailable.', 'cf-02-support-appeals-case-management'), ['status' => 503, 'trace_id' => RequestGuard::traceId()]);
    }

    public function appealEligibility(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'appeal.eligibility');
            $appeal = $this->operations->appealForActor((string) $request['id'], $context);
            RuntimeWorkflowPolicy::assertAppeal((string) $appeal['state'], 'eligibility_review');
            $first = $this->operations->mutateAppeal((string) $request['id'], $context, RequestGuard::expectedVersion($request), ['state' => 'eligibility_review'], 'AppealSubmitted', 'appeal_eligibility', RequestGuard::idempotencyKey($request) . ':review', [], $this->now());
            $eligible = ApiInput::boolean($request->get_param('eligible'), 'Appeal eligibility');
            $to = $eligible ? 'accepted' : 'rejected';
            RuntimeWorkflowPolicy::assertAppeal((string) $first['state'], $to);
            return $this->operations->mutateAppeal((string) $request['id'], $context, (int) $first['record_version'], ['state' => $to], $eligible ? 'AppealAccepted' : 'AppealRejected', 'appeal_eligibility', RequestGuard::idempotencyKey($request) . ':decision', [
                'reason' => sanitize_textarea_field((string) $request->get_param('reason')),
                'further_path' => sanitize_text_field((string) $request->get_param('further_path')),
                'time_exception' => ApiInput::boolean($request->get_param('time_exception'), 'Appeal time-exception flag', false),
            ], $this->now());
        });
    }

    public function appealConflictCheck(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'appeal.queue.read','appeal.review');
            return $this->operations->conflictCheck(
                (string) $request['id'], sanitize_text_field((string) $request->get_param('reviewer_ref')), $context
            );
        });
    }

    public function assignAppealReviewer(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'appeal.review','appeal.queue.read');
            $appeal = $this->operations->appealForActor((string) $request['id'], $context);
            RuntimeWorkflowPolicy::assertAppeal((string) $appeal['state'], 'under_review');
            $reviewer = sanitize_text_field((string) $request->get_param('reviewer_ref'));
            $conflict = $reviewer === '' ? ['eligible' => false] : $this->operations->conflictCheck((string) $request['id'], $reviewer, $context);
            if (($conflict['eligible'] ?? false) !== true) {
                throw new RuntimeException('Reviewer independence, competence or availability requirements are not met.');
            }
            return $this->operations->mutateAppeal((string) $request['id'], $context, RequestGuard::expectedVersion($request), ['state' => 'under_review','reviewer_ref' => $reviewer], 'AppealReviewerAssigned', 'appeal_reviewer_assignment', RequestGuard::idempotencyKey($request), [
                'reviewer_ref' => $reviewer, 'competence_ref' => sanitize_text_field((string) $request->get_param('competence_ref')),
            ], $this->now());
        });
    }

    public function requestAppealNativeAction(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'appeal.native.request');
            RequestGuard::requireRecentAuthentication($context, $this->now());
            $appeal = $this->operations->appealForActor((string) $request['id'], $context);
            RuntimeWorkflowPolicy::assertAppeal((string) $appeal['state'], 'native_decision_pending');
            $command = $this->operations->createNativeCommand(
                SupportCaseId::fromString((string) $appeal['case_uuid']), $context,
                sanitize_key((string) $request->get_param('native_owner')),
                sanitize_key((string) $request->get_param('action')),
                (string) $appeal['original_decision_ref'], (int) $request->get_param('expected_native_version'),
                is_array($request->get_json_params()) ? $request->get_json_params() : [],
                $this->cipher->encrypt(wp_json_encode(is_array($request->get_json_params()) ? $request->get_json_params() : [], JSON_THROW_ON_ERROR)),
                RequestGuard::idempotencyKey($request), 'appeal_native_action', $this->now()
            );
            return $this->operations->mutateAppeal((string) $request['id'], $context, RequestGuard::expectedVersion($request), ['state' => 'native_decision_pending','native_command_ref' => (string) $command['command_uuid']], 'AppealImplementationRequested', 'appeal_native_action', RequestGuard::idempotencyKey($request) . ':appeal', ['command_ref' => $command['command_uuid']], $this->now());
        }, 202);
    }

    public function recordAppealDecision(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'appeal.decision');
            $appeal = $this->operations->appealForActor((string) $request['id'], $context);
            RuntimeWorkflowPolicy::assertAppeal((string) $appeal['state'], 'decided');
            $outcome = sanitize_key((string) $request->get_param('outcome'));
            if (!in_array($outcome, ['uphold','modify','overturn','remand','withdraw'], true)
                || trim((string) $request->get_param('findings')) === ''
                || in_array($outcome, ['modify','overturn'], true) && trim((string) $request->get_param('effective_actions')) === '') {
                throw new RuntimeException('A complete reasoned appeal decision is required.');
            }
            return $this->operations->mutateAppeal((string) $request['id'], $context, RequestGuard::expectedVersion($request), ['state' => 'decided','outcome' => $outcome], 'AppealDecided', 'appeal_decision', RequestGuard::idempotencyKey($request), [
                'outcome' => $outcome, 'policy_version' => sanitize_text_field((string) $request->get_param('policy_version')),
                'findings' => ApiInput::safeTextarea($request->get_param('findings'), 20000, true),
                'evidence_considered' => ApiInput::referenceList($request->get_param('evidence_considered')),
                'effective_actions' => ApiInput::safeTextarea($request->get_param('effective_actions'), 10000, in_array($outcome, ['modify','overturn'], true)),
                'further_rights' => ApiInput::safeTextarea($request->get_param('further_rights'), 10000, true),
            ], $this->now());
        });
    }

    public function confirmAppealImplementation(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'appeal.implementation.confirm');
            $appeal = $this->operations->appealForActor((string) $request['id'], $context);
            RuntimeWorkflowPolicy::assertAppeal((string) $appeal['state'], 'implemented');
            $ref = sanitize_text_field((string) $request->get_param('implementation_ref'));
            if ($ref === '' || !ApiInput::boolean($request->get_param('native_version_matches'), 'Native-version match flag', false)) {
                throw new RuntimeException('Native implementation evidence is incomplete or drifted.');
            }
            return $this->operations->mutateAppeal((string) $request['id'], $context, RequestGuard::expectedVersion($request), ['state' => 'implemented','implementation_ref' => $ref], 'AppealImplemented', 'appeal_implementation', RequestGuard::idempotencyKey($request), ['implementation_ref' => $ref], $this->now());
        });
    }

    public function remandAppeal(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'appeal.decision');
            $appeal = $this->operations->appealForActor((string) $request['id'], $context);
            RuntimeWorkflowPolicy::assertAppeal((string) $appeal['state'], 'under_review');
            return $this->operations->mutateAppeal((string) $request['id'], $context, RequestGuard::expectedVersion($request), ['state' => 'under_review','outcome' => 'remand'], 'AppealDecided', 'appeal_remand', RequestGuard::idempotencyKey($request), ['reason' => sanitize_textarea_field((string) $request->get_param('reason'))], $this->now());
        });
    }

    public function closeAppeal(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'appeal.decision');
            $appeal = $this->operations->appealForActor((string) $request['id'], $context);
            RuntimeWorkflowPolicy::assertAppeal((string) $appeal['state'], 'closed');
            if (trim((string) $appeal['implementation_ref']) === '') {
                throw new RuntimeException('Appeal cannot close before native implementation reconciliation.');
            }
            return $this->operations->mutateAppeal((string) $request['id'], $context, RequestGuard::expectedVersion($request), ['state' => 'closed'], 'AppealClosed', 'appeal_closure', RequestGuard::idempotencyKey($request), ['notice_sent' => ApiInput::boolean($request->get_param('notice_sent'), 'Appeal notice flag', false)], $this->now());
        });
    }

    public function stageConfiguration(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'configuration.stage');
            RequestGuard::requireRecentAuthentication($context, $this->now());
            $config = $request->get_param('configuration');
            $approvals = $request->get_param('approvals');
            return $this->operations->stageConfiguration($context, sanitize_key((string) $request->get_param('key')), is_array($config) ? $config : [], is_array($approvals) ? $approvals : [], RequestGuard::idempotencyKey($request), $this->now());
        }, 201);
    }

    public function activateConfiguration(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'configuration.activate');
            RequestGuard::requireRecentAuthentication($context, $this->now());
            return $this->operations->activateConfiguration((string) $request['key'], (int) $request['version'], $context, RequestGuard::approvalReference($request), RequestGuard::idempotencyKey($request), $this->now());
        });
    }

    public function rollbackConfiguration(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'configuration.rollback');
            RequestGuard::requireRecentAuthentication($context, $this->now());
            // Rollback is an activation of an earlier staged/superseded immutable snapshot.
            return $this->operations->rollbackConfiguration((string) $request['key'], (int) $request['version'], $context, RequestGuard::approvalReference($request), RequestGuard::idempotencyKey($request), $this->now());
        });
    }

    public function slaAtRisk(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'sla.manage','queue.manage','queue.assigned.read');
            return ['items' => $this->operations->slaAtRisk($context, $this->limit($request))];
        });
    }

    public function nativeCommandStatus(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'native.action.request','appeal.native.request','reconciliation.manage');
            return $this->operations->commandStatus((string) $request['id'], $context);
        });
    }

    public function linkedDomainProjection(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'case.assigned.read','case.specialist.read','case.own.read','case.represented.read');
            return ['items' => $this->operations->linkedDomainProjection($this->caseId($request), $context)];
        });
    }

    public function exportStatus(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'export.bounded.request','audit.sample.read');
            RequestGuard::requireRecentAuthentication($context, $this->now());
            return $this->operations->exportStatus($this->caseId($request), $context);
        });
    }

    public function purgeReconciliation(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'retention.review','reconciliation.manage');
            return ['items' => $this->operations->purgeReconciliation((string) $request['id'], $context)];
        });
    }

    public function slaMetrics(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function (): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'metrics.read','metrics.privacy_safe.read');
            return $this->operations->slaMetrics();
        });
    }

    public function reopenMetrics(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function (): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'metrics.read','metrics.privacy_safe.read');
            return $this->operations->reopenMetrics();
        });
    }

    public function backlogHealth(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function (): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'metrics.read','metrics.privacy_safe.read');
            return $this->operations->backlogHealth();
        });
    }

    public function qualitySample(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'quality.manage','audit.sample.read');
            return ['items' => $this->operations->qualitySample($this->limit($request))];
        });
    }

    public function retentionDue(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), 'retention.review');
            return ['items' => $this->operations->dueRetention($this->limit($request))];
        });
    }

    private function caseTransition(\WP_REST_Request $request, string $to, string $command, string $event, array $capabilities, string $purpose): \WP_REST_Response|\WP_Error
    {
        return $this->run(function () use ($request, $to, $command, $event, $capabilities, $purpose): array {
            $context = $this->context();
            RequestGuard::requireCapability($context, $this->now(), ...$capabilities);
            $case = $this->operations->caseForActor($this->caseId($request), $context);
            RuntimeWorkflowPolicy::assertCase((string) $case['state'], $to);
            $reason = sanitize_textarea_field((string) $request->get_param('reason'));
            if ($reason === '') {
                throw new RuntimeException('A reason is required for this state change.');
            }
            return $this->operations->mutateCase(
                $this->caseId($request), $context, RequestGuard::expectedVersion($request), ['state' => $to],
                $command, $event, $purpose, RequestGuard::idempotencyKey($request), ['reason' => $reason], $this->now()
            );
        });
    }

    private function decryptProjection(array $projection): array
    {
        foreach ($projection['messages'] ?? [] as $index => $message) {
            if (!is_array($message)) {
                continue;
            }
            try {
                $projection['messages'][$index]['body'] = $this->cipher->decrypt((string) $message['body_ciphertext']);
            } catch (Throwable) {
                $projection['messages'][$index]['body'] = null;
                $projection['messages'][$index]['integrity_error'] = true;
            }
            unset($projection['messages'][$index]['body_ciphertext']);
        }
        return $projection;
    }

    private function context(): PrincipalContext { return $this->contexts->current($this->now()); }
    private function now(): DateTimeImmutable { return new DateTimeImmutable('now', new DateTimeZone('UTC')); }
    private function caseId(\WP_REST_Request $request): SupportCaseId { return SupportCaseId::fromString((string) $request['id']); }
    private function limit(\WP_REST_Request $request): int { return max(1, min(100, (int) ($request->get_param('limit') ?: 50))); }

    /** @param callable():array<string,mixed> $callback */
    private function run(callable $callback, int $successStatus = 200): \WP_REST_Response|\WP_Error
    {
        try {
            return new \WP_REST_Response($callback(), $successStatus, [
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (PublicApiException $error) {
            return new \WP_Error($error->publicCode(), $error->publicMessage(), ['status'=>$error->httpStatus(),'trace_id'=>RequestGuard::traceId()]);
        } catch (\Sabri\CF02\Domain\ConcurrencyConflict|\DomainException $error) {
            $trace = RequestGuard::traceId();
            do_action('cf02_api_conflict', ['trace_id'=>$trace,'error_class'=>$error::class]);
            return new \WP_Error('cf02_conflict', __('The record changed. Refresh it and retry.', 'cf-02-support-appeals-case-management'), ['status'=>409,'trace_id'=>$trace]);
        } catch (Throwable $error) {
            $trace = RequestGuard::traceId();
            do_action('cf02_api_request_failed', ['trace_id'=>$trace,'error_class'=>$error::class,'error'=>$error]);
            return new \WP_Error('cf02_request_rejected', __('The request was rejected. Review the fields and your current authorization, then retry.', 'cf-02-support-appeals-case-management'), ['status'=>422,'trace_id'=>$trace]);
        }
    }
}
