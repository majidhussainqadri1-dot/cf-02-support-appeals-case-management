<?php

declare(strict_types=1);

namespace Sabri\CF02\Future;

use DateTimeImmutable;
use InvalidArgumentException;
use Sabri\CF02\Governance\ServiceEqualityPolicy;
use Sabri\CF02\Safety\EmergencyRunbookRegistry;
use Sabri\CF02\Security\SensitiveContentDetector;

final class AssistanceIntelligence
{
    /** @param array<string,mixed> $case @return array<string,mixed> */
    public function supportCopilot(array $case): array
    {
        ServiceEqualityPolicy::assertNoPrivilegeSignals($case);
        $summary = trim((string)($case['summary'] ?? ''));
        $category = trim((string)($case['category'] ?? ''));
        if ($summary === '' || $category === '') {
            throw new InvalidArgumentException('Copilot requires a case summary and category.');
        }
        if (SensitiveContentDetector::containsProhibitedSecret($summary)) {
            throw new InvalidArgumentException('Copilot input contains prohibited secret material.');
        }
        $missing = [];
        foreach ((array)($case['required_fields'] ?? []) as $field) {
            if (is_string($field) && !array_key_exists($field, (array)($case['fields'] ?? []))) {
                $missing[] = $field;
            }
        }
        return [
            'feature_id'=>'CF02-FUT-001',
            'summary'=>$summary,
            'category'=>$category,
            'missing_information'=>array_values(array_unique($missing)),
            'draft_response'=>'A governed editable draft may be prepared after the missing information and policy references are verified.',
            'final_decision_allowed'=>false,
            'human_review_required'=>true,
            'native_owner_boundary'=>'No final appeal, refund, identity, clinical, safety or native-owner mutation is authorized.',
        ];
    }

    /** @param list<array{match:string,step:string}> $playbook @return array<string,mixed> */
    public function selfServiceResolution(string $description, array $playbook): array
    {
        $description = trim($description);
        if ($description === '') {
            throw new InvalidArgumentException('Self-service description is required.');
        }
        if (SensitiveContentDetector::containsProhibitedSecret($description)) {
            throw new InvalidArgumentException('Sensitive credentials cannot be processed by pre-ticket self service.');
        }
        $emergency = EmergencyRunbookRegistry::classifyText($description);
        if ($emergency !== null) {
            return ['feature_id'=>'CF02-FUT-002','resolved'=>false,'divert_to_governed_intake'=>true,'emergency_type'=>$emergency,'steps'=>[]];
        }
        $steps=[];
        foreach ($playbook as $row) {
            if (!isset($row['match'],$row['step']) || trim((string)$row['match'])==='' || trim((string)$row['step'])==='') {
                throw new InvalidArgumentException('Malformed self-service playbook row.');
            }
            if (stripos($description, (string)$row['match']) !== false) {
                $steps[] = trim((string)$row['step']);
            }
        }
        return ['feature_id'=>'CF02-FUT-002','resolved'=>false,'divert_to_governed_intake'=>false,'emergency_type'=>null,'steps'=>array_slice(array_values(array_unique($steps)),0,5),'case_creation_remains_available'=>true];
    }

    /** @param array<string,mixed> $signals @return array<string,mixed> */
    public function caseRiskRadar(array $signals, DateTimeImmutable $now): array
    {
        ServiceEqualityPolicy::assertNoPrivilegeSignals($signals);
        $score=0;
        $reasons=[];
        $deadline=(string)($signals['deadline_at'] ?? '');
        if ($deadline !== '') {
            $deadlineAt=new DateTimeImmutable($deadline);
            $seconds=$deadlineAt->getTimestamp()-$now->getTimestamp();
            if ($seconds <= 0) { $score+=45; $reasons[]='deadline_breached'; }
            elseif ($seconds <= 3600) { $score+=30; $reasons[]='deadline_within_one_hour'; }
            elseif ($seconds <= 14400) { $score+=15; $reasons[]='deadline_within_four_hours'; }
        }
        $transfers=(int)($signals['transfer_count'] ?? 0);
        $reopens=(int)($signals['reopen_count'] ?? 0);
        $providerFailures=(int)($signals['provider_failure_count'] ?? 0);
        if ($transfers > 2) { $score+=min(20,($transfers-2)*5); $reasons[]='excessive_transfers'; }
        if ($reopens > 0) { $score+=min(15,$reopens*5); $reasons[]='reopened_case'; }
        if ($providerFailures > 0) { $score+=min(25,$providerFailures*10); $reasons[]='provider_delivery_failure'; }
        if ((bool)($signals['user_waiting'] ?? false)) { $score+=10; $reasons[]='waiting_on_support'; }
        $score=min(100,$score);
        $level=$score>=70?'critical':($score>=45?'high':($score>=20?'medium':'low'));
        return ['feature_id'=>'CF02-FUT-003','risk_score'=>$score,'risk_level'=>$level,'reasons'=>array_values(array_unique($reasons)),'privilege_signals_consumed'=>false];
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    public function nextBestAction(array $context): array
    {
        ServiceEqualityPolicy::assertNoPrivilegeSignals($context);
        $state=(string)($context['state'] ?? '');
        if ($state==='') throw new InvalidArgumentException('Case state is required for next-best-action.');
        if ((bool)($context['emergency'] ?? false)) {
            return ['feature_id'=>'CF02-FUT-004','action'=>'escalate_emergency','execution'=>'human_or_native_owner','authorized_to_execute'=>false];
        }
        if ((bool)($context['delivery_failed'] ?? false)) {
            return ['feature_id'=>'CF02-FUT-004','action'=>'repair_or_retry_delivery','execution'=>'governed_workflow','authorized_to_execute'=>false];
        }
        if ((bool)($context['missing_information'] ?? false)) {
            return ['feature_id'=>'CF02-FUT-004','action'=>'request_information','execution'=>'editable_suggestion','authorized_to_execute'=>false];
        }
        if ((bool)($context['native_owner_action_required'] ?? false)) {
            return ['feature_id'=>'CF02-FUT-004','action'=>'issue_native_owner_command','execution'=>'canonical_owner_command','authorized_to_execute'=>false];
        }
        return ['feature_id'=>'CF02-FUT-004','action'=>'continue_case_review','execution'=>'editable_suggestion','authorized_to_execute'=>false];
    }
}
