<?php

declare(strict_types=1);

namespace Sabri\CF02\Future;

use DateTimeImmutable;
use InvalidArgumentException;
use Sabri\CF02\Security\SensitiveContentDetector;

final class ContinuityExperienceIntelligence
{
    /** @param array<string,mixed> $incident @return array<string,mixed> */
    public function publicStatus(array $incident): array
    {
        foreach (['incident_id','service_key','status','public_summary','next_update_at'] as $key) if (trim((string)($incident[$key] ?? ''))==='') throw new InvalidArgumentException('Public status incident is incomplete.');
        $summary=(string)$incident['public_summary'];
        if (SensitiveContentDetector::containsProhibitedSecret($summary)) throw new InvalidArgumentException('Public incident summary contains sensitive material.');
        foreach (['case_id','user_id','reporter','email','phone','attachment','internal_note'] as $forbidden) if (array_key_exists($forbidden,$incident)) throw new InvalidArgumentException('Public status cannot expose case/user/private fields.');
        return ['feature_id'=>'CF02-FUT-015','incident_id'=>(string)$incident['incident_id'],'service_key'=>(string)$incident['service_key'],'status'=>(string)$incident['status'],'public_summary'=>$summary,'next_update_at'=>(string)$incident['next_update_at'],'privacy_safe'=>true];
    }

    /** @return array<string,mixed> */
    public function incidentSubscription(string $incidentId,string $channel,string $destinationHash,DateTimeImmutable $now,DateTimeImmutable $expiresAt): array
    {
        if (trim($incidentId)==='' || !in_array($channel,['email','push','in_app'],true) || preg_match('/^[a-f0-9]{64}$/',$destinationHash)!==1 || $expiresAt<=$now) throw new InvalidArgumentException('Incident subscription request is invalid.');
        return ['feature_id'=>'CF02-FUT-016','incident_id'=>$incidentId,'channel'=>$channel,'destination_hash'=>$destinationHash,'expires_at'=>$expiresAt->format(DATE_ATOM),'other_reporters_visible'=>false,'unrelated_cases_visible'=>false];
    }

    /** @return array<string,mixed> */
    public function translationDraft(string $sourceText,string $sourceLocale,string $targetLocale,string $riskClass='standard'): array
    {
        if (trim($sourceText)==='' || preg_match('/^[a-z]{2,3}(?:-[A-Z]{2})?$/',$sourceLocale)!==1 || preg_match('/^[a-z]{2,3}(?:-[A-Z]{2})?$/',$targetLocale)!==1) throw new InvalidArgumentException('Translation locales or text are invalid.');
        if (SensitiveContentDetector::containsProhibitedSecret($sourceText)) throw new InvalidArgumentException('Sensitive credentials cannot be sent to translation drafting.');
        if (!in_array($riskClass,['standard','clinical','legal','safety','financial','identity'],true)) throw new InvalidArgumentException('Unknown translation risk class.');
        return ['feature_id'=>'CF02-FUT-017','source_locale'=>$sourceLocale,'target_locale'=>$targetLocale,'source_text'=>$sourceText,'draft_text'=>null,'translation_status'=>'draft-provider-required','human_review_required'=>$riskClass!=='standard','authoritative'=>false,'risk_class'=>$riskClass];
    }

    /** @param array<string,mixed> $preferences @return array<string,mixed> */
    public function accessibilityProfile(array $preferences): array
    {
        $allowed=['preferred_locale','screen_reader','large_text','simplified_language','reduced_motion','high_contrast','representative_communication','preferred_channel'];
        $out=[];
        foreach ($preferences as $key=>$value) {
            if (!in_array($key,$allowed,true)) throw new InvalidArgumentException('Accessibility support profile contains a non-preference field.');
            if (in_array($key,['screen_reader','large_text','simplified_language','reduced_motion','high_contrast','representative_communication'],true) && !is_bool($value)) throw new InvalidArgumentException('Accessibility preference type is invalid.');
            $out[$key]=$value;
        }
        return ['feature_id'=>'CF02-FUT-018','preferences'=>$out,'identity_authority_stored'=>false,'representative_authority_stored'=>false];
    }

    /** @return array<string,mixed> */
    public function lowBandwidthDraft(string $draft,string $dataClass,bool $online): array
    {
        if (trim($draft)==='') throw new InvalidArgumentException('Low-bandwidth draft cannot be empty.');
        if (!in_array($dataClass,['C1','C2','C3','C4','C5'],true)) throw new InvalidArgumentException('Unknown data classification.');
        if (!$online && in_array($dataClass,['C4','C5'],true)) return ['feature_id'=>'CF02-FUT-019','queued'=>false,'offline_cache_allowed'=>false,'reason'=>'Sensitive C4/C5 case data cannot be stored in uncontrolled offline cache.'];
        return ['feature_id'=>'CF02-FUT-019','queued'=>!$online,'offline_cache_allowed'=>!$online,'sync_required'=>!$online,'data_class'=>$dataClass,'draft_hash'=>hash('sha256',$draft)];
    }

    /** @return array<string,mixed> */
    public function mobileEnvelope(string $caseReference,string $backendContractVersion,string $platform,string $deepLinkToken): array
    {
        if (trim($caseReference)==='' || trim($backendContractVersion)==='' || !in_array($platform,['ios','android'],true) || trim($deepLinkToken)==='') throw new InvalidArgumentException('Native mobile support envelope is invalid.');
        return ['feature_id'=>'CF02-FUT-020','case_reference'=>$caseReference,'backend_contract_version'=>$backendContractVersion,'platform'=>$platform,'deep_link_token'=>$deepLinkToken,'canonical_case_truth'=>'CF-02 backend','duplicate_mobile_database'=>false];
    }
}
