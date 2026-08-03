<?php

declare(strict_types=1);

namespace Sabri\CF02\Authorization;

use DateTimeImmutable;
use InvalidArgumentException;
use Sabri\CF02\Domain\SupportCaseId;

final class PurposeBoundAccessPolicy
{
    private const ACTION_CAPABILITIES = [
        'view_case' => 'case.view',
        'reply_case' => 'case.reply',
        'manage_tasks' => 'case.tasks',
        'search_case' => 'case.search',
        'restricted_projection' => 'case.restricted',
        'export_case' => 'case.export',
        'native_command' => 'native.command',
        'appeal_review' => 'appeal.review',
        'quality_review' => 'quality.review',
        'configuration_change' => 'configuration.change',
    ];

    private const PURPOSES = [
        'case.support',
        'case.escalation',
        'appeal.review',
        'privacy.rights',
        'security.liaison',
        'clinical.liaison',
        'quality.assurance',
        'audit.sample',
        'legal.hold',
        'configuration.change',
    ];

    public function decide(
        AccessContext $context,
        SupportCaseId $caseId,
        string $queueKey,
        string $fieldClass,
        string $action,
        DateTimeImmutable $at
    ): AccessDecision {
        if (!isset(self::ACTION_CAPABILITIES[$action])) {
            throw new InvalidArgumentException('Unknown access action.');
        }
        if (!in_array($fieldClass, ['C1', 'C2', 'C3', 'C4', 'C5'], true)) {
            throw new InvalidArgumentException('Unknown field classification.');
        }
        if (preg_match('/^[a-z][a-z0-9_]*$/', $queueKey) !== 1) {
            throw new InvalidArgumentException('Invalid queue key.');
        }
        if (!$context->isActiveAt($at)) {
            return AccessDecision::deny('Access context is inactive, expired or suspended.');
        }
        if (!in_array($context->purpose(), self::PURPOSES, true)) {
            return AccessDecision::deny('The declared purpose is not recognized by the CF-02 access constitution.');
        }
        $required = self::ACTION_CAPABILITIES[$action];
        if (!$context->hasCapability($required)) {
            return AccessDecision::deny('The required versioned capability is absent.');
        }

        $caseBoundActions = ['view_case', 'reply_case', 'manage_tasks', 'restricted_projection', 'export_case', 'native_command', 'appeal_review'];
        if (in_array($action, $caseBoundActions, true)
            && !$context->isAssignedTo($caseId)
            && !$context->isAssignedQueue($queueKey)) {
            return AccessDecision::deny('Actor is not assigned to the case or its authorized queue.');
        }

        if ($action === 'quality_review' && !$context->hasRole('quality_reviewer')) {
            return AccessDecision::deny('Quality review requires an independent quality-reviewer role.');
        }
        if ($action === 'appeal_review' && !$context->hasRole('appeal_reviewer')) {
            return AccessDecision::deny('Appeal review requires the independent appeal-reviewer role.');
        }
        if ($action === 'configuration_change' && !$context->hasRole('configuration_approver')) {
            return AccessDecision::deny('Configuration mutation requires a configuration approver.');
        }

        if (in_array($fieldClass, ['C4', 'C5'], true)) {
            if (!$context->sensitiveApproval()) {
                return AccessDecision::deny('Sensitive field access lacks purpose-bound specialist approval.');
            }
            if (!$context->hasRecentAuthentication($at)) {
                return AccessDecision::deny('Sensitive field access requires recent authentication.');
            }
        }

        if ($action === 'restricted_projection' && !in_array($context->purpose(), ['privacy.rights', 'security.liaison', 'clinical.liaison', 'appeal.review', 'legal.hold'], true)) {
            return AccessDecision::deny('Restricted projection is incompatible with the declared purpose.');
        }
        if ($action === 'native_command' && !in_array($context->purpose(), ['case.escalation', 'appeal.review', 'privacy.rights', 'security.liaison'], true)) {
            return AccessDecision::deny('Native-owner command is incompatible with the declared purpose.');
        }
        if ($action === 'export_case' && !in_array($context->purpose(), ['privacy.rights', 'legal.hold', 'appeal.review'], true)) {
            return AccessDecision::deny('Case export is incompatible with the declared purpose.');
        }

        return AccessDecision::allow('Capability, assignment, purpose, classification and freshness requirements are satisfied.');
    }
}
