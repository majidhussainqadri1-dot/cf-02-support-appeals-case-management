<?php

declare(strict_types=1);

namespace Sabri\CF02\Future;

use DateTimeImmutable;
use InvalidArgumentException;
use Sabri\CF02\Appeal\AppealEligibilityPolicy;
use Sabri\CF02\Security\SensitiveContentDetector;

final class AppealIntelligence
{
    /** @param list<string> $grounds @return array<string,mixed> */
    public function eligibilityPreview(string $decisionId,string $appellant,bool $hasStanding,DateTimeImmutable $decisionAt,DateTimeImmutable $submittedAt,int $deadlineDays,array $grounds,bool $hasNewEvidence,bool $exceptionRequested=false,?string $exceptionReason=null): array
    {
        $decision=(new AppealEligibilityPolicy())->decide($decisionId,$appellant,$hasStanding,$decisionAt,$submittedAt,$deadlineDays,$grounds,$hasNewEvidence,$exceptionRequested,$exceptionReason);
        return ['feature_id'=>'CF02-FUT-007','eligible_preview'=>$decision->eligible(),'exception_applied'=>$decision->exceptionApplied(),'reasons'=>$decision->reasons(),'further_path'=>$decision->furtherPath(),'binding'=>false,'final_eligibility_authority'=>'governed appeal workflow'];
    }

    /** @param list<array<string,mixed>> $references @return array<string,mixed> */
    public function evidenceRoom(string $appealId, array $references): array
    {
        if (trim($appealId)==='') throw new InvalidArgumentException('Appeal evidence room requires an appeal ID.');
        $normalized=[];
        foreach ($references as $ref) {
            $owner=(string)($ref['owner'] ?? ''); $type=(string)($ref['type'] ?? ''); $id=(string)($ref['reference'] ?? ''); $version=(string)($ref['version'] ?? ''); $hash=(string)($ref['snapshot_hash'] ?? '');
            if ($owner===''||$type===''||$id===''||$version===''||preg_match('/^[a-f0-9]{64}$/',$hash)!==1) throw new InvalidArgumentException('Appeal evidence reference is incomplete.');
            if (array_key_exists('raw_payload',$ref) || array_key_exists('projection_json',$ref) || array_key_exists('domain_truth',$ref)) throw new InvalidArgumentException('Appeal evidence room cannot duplicate native-domain truth.');
            $normalized[]=['owner'=>$owner,'type'=>$type,'reference'=>$id,'version'=>$version,'snapshot_hash'=>$hash,'viewer_scope'=>(string)($ref['viewer_scope'] ?? 'appeal_reviewer')];
        }
        return ['feature_id'=>'CF02-FUT-008','appeal_id'=>$appealId,'references'=>$normalized,'storage_model'=>'typed-reference-version-hash-only','raw_native_truth_copied'=>false];
    }

    /** @param array<string,mixed> $original @param array<string,mixed> $appeal @return array<string,mixed> */
    public function decisionDifference(array $original,array $appeal): array
    {
        foreach ([$original,$appeal] as $decision) {
            foreach (['outcome','reason','policy_version'] as $required) if (trim((string)($decision[$required] ?? ''))==='') throw new InvalidArgumentException('Decision comparison requires outcome, reason and policy version.');
        }
        $redact=static function(string $value):string { return SensitiveContentDetector::containsProhibitedSecret($value)?'[redacted-sensitive-material]':$value; };
        return [
            'feature_id'=>'CF02-FUT-009','outcome_changed'=>$original['outcome']!==$appeal['outcome'],
            'original'=>['outcome'=>(string)$original['outcome'],'reason'=>$redact((string)$original['reason']),'policy_version'=>(string)$original['policy_version']],
            'appeal'=>['outcome'=>(string)$appeal['outcome'],'reason'=>$redact((string)$appeal['reason']),'policy_version'=>(string)$appeal['policy_version']],
            'difference_summary'=>$original['outcome']===$appeal['outcome']?'Outcome upheld; reasons/policy may still differ.':'Outcome modified or overturned by appeal review.',
        ];
    }

    /** @return array<string,mixed> */
    public function serviceComplaint(string $caseId,string $complainedHandler,string $reviewer,string $reason,bool $reviewerConflictFree,bool $reviewerIndependent): array
    {
        foreach ([$caseId,$complainedHandler,$reviewer,$reason] as $value) if (trim($value)==='') throw new InvalidArgumentException('Support-service complaint fields are required.');
        if ($complainedHandler===$reviewer || !$reviewerConflictFree || !$reviewerIndependent) {
            return ['feature_id'=>'CF02-FUT-010','accepted'=>false,'reason'=>'Independent conflict-free second-level reviewer is required.','retaliation_prohibited'=>true];
        }
        return ['feature_id'=>'CF02-FUT-010','accepted'=>true,'reason'=>'Complaint accepted for independent support-service review.','original_case_id'=>$caseId,'reviewer'=>$reviewer,'retaliation_prohibited'=>true];
    }
}
