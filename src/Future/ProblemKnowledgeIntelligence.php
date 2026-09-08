<?php

declare(strict_types=1);

namespace Sabri\CF02\Future;

use InvalidArgumentException;
use Sabri\CF02\Security\SensitiveContentDetector;

final class ProblemKnowledgeIntelligence
{
    /** @param list<string> $symptoms @return array<string,mixed> */
    public function problemFingerprint(string $serviceKey, string $category, array $symptoms, ?string $rootCause = null, ?string $workaround = null, ?string $fixVersion = null): array
    {
        foreach ([$serviceKey,$category] as $value) {
            if (preg_match('/^[a-z][a-z0-9_.-]{1,63}$/',$value)!==1) throw new InvalidArgumentException('Problem service/category key is invalid.');
        }
        $normalized=[];
        foreach ($symptoms as $symptom) {
            $symptom=strtolower(trim($symptom));
            if ($symptom==='' || SensitiveContentDetector::containsProhibitedSecret($symptom)) throw new InvalidArgumentException('Problem symptom is empty or sensitive.');
            $normalized[]=$symptom;
        }
        $normalized=array_values(array_unique($normalized)); sort($normalized);
        if ($normalized===[]) throw new InvalidArgumentException('At least one problem symptom is required.');
        $fingerprint=hash('sha256',$serviceKey.'|'.$category.'|'.implode('|',$normalized));
        return [
            'feature_id'=>'CF02-FUT-005','problem_fingerprint'=>$fingerprint,'service_key'=>$serviceKey,'category'=>$category,
            'symptoms'=>$normalized,'root_cause'=>$rootCause===null?null:trim($rootCause),'workaround'=>$workaround===null?null:trim($workaround),'fix_version'=>$fixVersion===null?null:trim($fixVersion),
            'case_link_semantics'=>'reference-only; individual cases remain separate and cannot be auto-closed by problem resolution',
        ];
    }

    /** @param array<string,int> $unresolvedByTopic @param array<string,bool> $publishedCoverage @return list<array<string,mixed>> */
    public function knowledgeGaps(array $unresolvedByTopic, array $publishedCoverage, int $privacyThreshold = 5): array
    {
        if ($privacyThreshold < 3) throw new InvalidArgumentException('Knowledge-gap privacy threshold is too low.');
        $gaps=[];
        foreach ($unresolvedByTopic as $topic=>$count) {
            if (!is_string($topic) || preg_match('/^[a-z][a-z0-9_.-]{1,63}$/',$topic)!==1 || !is_int($count) || $count<0) throw new InvalidArgumentException('Malformed knowledge-gap aggregate.');
            if ($count<$privacyThreshold || (($publishedCoverage[$topic] ?? false)===true)) continue;
            $gaps[]=['feature_id'=>'CF02-FUT-006','topic'=>$topic,'unresolved_count'=>$count,'draft_required'=>true,'publication_requires_human_approval'=>true,'privacy_threshold'=>$privacyThreshold];
        }
        usort($gaps,static fn(array $a,array $b):int=>$b['unresolved_count']<=>$a['unresolved_count'] ?: strcmp($a['topic'],$b['topic']));
        return $gaps;
    }
}
