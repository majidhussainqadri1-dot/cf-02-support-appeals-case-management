<?php

declare(strict_types=1);

namespace Sabri\CF02\Automation;

use InvalidArgumentException;

final class AutomationGuard
{
    /** @return array{allowed:bool,human_review_required:bool,native_owner_required:bool,reasons:list<string>,automation_version:string} */
    public function decide(
        AutomationAction $action,
        float $confidence,
        string $automationVersion,
        bool $sensitiveCase,
        bool $humanConfirmed = false
    ): array {
        if ($confidence < 0.0 || $confidence > 1.0 || trim($automationVersion) === '') {
            throw new InvalidArgumentException('Invalid automation confidence or version.');
        }
        $forbidden = [
            AutomationAction::FinalAppealDecision,
            AutomationAction::IdentityHandover,
            AutomationAction::RefundApproval,
            AutomationAction::ClinicalAdvice,
            AutomationAction::SafetyClosure,
            AutomationAction::NativeMutation,
        ];
        if (in_array($action, $forbidden, true)) {
            return [
                'allowed' => false,
                'human_review_required' => true,
                'native_owner_required' => true,
                'reasons' => ['This action is reserved to a human or canonical native owner and cannot be automated.'],
                'automation_version' => $automationVersion,
            ];
        }
        if ($action === AutomationAction::AutoClose) {
            return [
                'allowed' => $humanConfirmed,
                'human_review_required' => true,
                'native_owner_required' => false,
                'reasons' => [$humanConfirmed ? 'Human confirmation permits the governed close workflow.' : 'Automatic closure is prohibited without governed human confirmation and notice eligibility.'],
                'automation_version' => $automationVersion,
            ];
        }
        $threshold = $sensitiveCase ? 0.90 : 0.75;
        if ($confidence < $threshold) {
            return [
                'allowed' => false,
                'human_review_required' => true,
                'native_owner_required' => false,
                'reasons' => ['Automation confidence is below the governed threshold.'],
                'automation_version' => $automationVersion,
            ];
        }
        return [
            'allowed' => true,
            'human_review_required' => true,
            'native_owner_required' => false,
            'reasons' => ['Automation may provide a marked suggestion or editable draft only.'],
            'automation_version' => $automationVersion,
        ];
    }
}
