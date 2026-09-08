<?php

declare(strict_types=1);

namespace Sabri\CF02\Future;

use InvalidArgumentException;
use Sabri\CF02\Governance\SupportParityAudit;

final class OperationsTransparencyIntelligence
{
    /** @param array<string,mixed> $scenario @return array<string,mixed> */
    public function trainingScenario(array $scenario): array
    {
        $required=['scenario_id','category','expected_route','expected_guardrail'];
        foreach ($required as $key) if (trim((string)($scenario[$key] ?? ''))==='') throw new InvalidArgumentException('Synthetic training scenario is incomplete.');
        foreach (['real_user_id','patient_id','email','phone','payment_reference','message_id','clinical_record_id','identity_document'] as $forbidden) {
            if (array_key_exists($forbidden,$scenario)) throw new InvalidArgumentException('Training lab accepts synthetic data only.');
        }
        if (($scenario['synthetic'] ?? null)!==true) throw new InvalidArgumentException('Training scenario must be explicitly marked synthetic.');
        return ['feature_id'=>'CF02-FUT-023','scenario_id'=>(string)$scenario['scenario_id'],'synthetic'=>true,'category'=>(string)$scenario['category'],'expected_route'=>(string)$scenario['expected_route'],'expected_guardrail'=>(string)$scenario['expected_guardrail'],'real_data_allowed'=>false];
    }

    /** @param array<string,mixed> $serviceMetrics @param array<string,mixed> $donorCohort @param array<string,mixed> $nonDonorCohort @return array<string,mixed> */
    public function transparencyCenter(string $period,array $serviceMetrics,array $donorCohort,array $nonDonorCohort,int $privacyThreshold=20): array
    {
        if (preg_match('/^\d{4}-\d{2}$/',$period)!==1 || $privacyThreshold<10) throw new InvalidArgumentException('Transparency period or privacy threshold is invalid.');
        $count=(int)($serviceMetrics['case_count'] ?? 0);
        if ($count<$privacyThreshold) return ['feature_id'=>'CF02-FUT-024','period'=>$period,'status'=>'suppressed','reason'=>'Aggregate cohort is below privacy publication threshold.'];
        $allowed=['case_count','first_response_seconds_p50','resolution_seconds_p50','reopen_rate','appeal_overturn_rate','accessibility_completion_rate','major_incident_count'];
        $public=[];
        foreach ($allowed as $key) if (array_key_exists($key,$serviceMetrics)) $public[$key]=$serviceMetrics[$key];
        $parity=(new SupportParityAudit())->evaluate($period,$donorCohort,$nonDonorCohort);
        return ['feature_id'=>'CF02-FUT-024','period'=>$period,'status'=>'published-aggregate','metrics'=>$public,'support_parity_status'=>$parity['status'],'privacy_threshold'=>$privacyThreshold,'individual_staff_scoring'=>false,'low_volume_identity_disclosure'=>false];
    }
}
