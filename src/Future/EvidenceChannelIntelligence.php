<?php

declare(strict_types=1);

namespace Sabri\CF02\Future;

use DateTimeImmutable;
use InvalidArgumentException;
use Sabri\CF02\Security\SensitiveContentDetector;

final class EvidenceChannelIntelligence
{
    /** @param list<string> $allowedSelectors @return array<string,mixed> */
    public function coBrowsingSession(string $sessionId,bool $consent,array $allowedSelectors,DateTimeImmutable $now,DateTimeImmutable $expiresAt,bool $remoteControl=false,bool $credentialCapture=false): array
    {
        if (trim($sessionId)==='' || !$consent || $expiresAt<=$now) throw new InvalidArgumentException('Co-browsing requires explicit consent, valid session ID and future expiry.');
        if ($remoteControl || $credentialCapture) throw new InvalidArgumentException('Unrestricted remote control and credential capture are prohibited.');
        $selectors=[];
        foreach ($allowedSelectors as $selector) {
            $selector=trim($selector); if ($selector==='' || str_contains(strtolower($selector),'password') || str_contains(strtolower($selector),'otp')) throw new InvalidArgumentException('Co-browsing selector is unsafe.');
            $selectors[]=$selector;
        }
        if ($selectors===[]) throw new InvalidArgumentException('At least one scoped co-browsing selector is required.');
        return ['feature_id'=>'CF02-FUT-011','session_id'=>$sessionId,'consent'=>true,'allowed_selectors'=>array_values(array_unique($selectors)),'expires_at'=>$expiresAt->format(DATE_ATOM),'remote_control'=>false,'credential_capture'=>false];
    }

    /** @return array<string,mixed> */
    public function voiceSupportHandoff(string $file17ConversationRef,string $caseId,bool $consent,?string $transcriptHash=null): array
    {
        if (!$consent || trim($file17ConversationRef)==='' || trim($caseId)==='') throw new InvalidArgumentException('Voice support requires consent, File 17 reference and case ID.');
        if ($transcriptHash!==null && preg_match('/^[a-f0-9]{64}$/',$transcriptHash)!==1) throw new InvalidArgumentException('Transcript hash must be SHA-256.');
        return ['feature_id'=>'CF02-FUT-012','case_id'=>$caseId,'file17_conversation_reference'=>$file17ConversationRef,'transcript_hash'=>$transcriptHash,'communication_owner'=>'File 17','cf02_storage'=>'case linkage and governed evidence reference only'];
    }

    /** @param array<string,mixed> $metadata @return array<string,mixed> */
    public function sanitizeEvidenceEnvelope(string $filename,string $textPreview,array $metadata): array
    {
        if (trim($filename)==='') throw new InvalidArgumentException('Evidence filename is required.');
        $warnings=SensitiveContentDetector::warnings($textPreview);
        $allowed=['mime_type','page_count','pixel_width','pixel_height','duration_seconds'];
        $sanitized=[];
        foreach ($allowed as $key) if (array_key_exists($key,$metadata)) $sanitized[$key]=$metadata[$key];
        $removed=array_values(array_diff(array_keys($metadata),$allowed));
        return ['feature_id'=>'CF02-FUT-013','filename'=>basename($filename),'sanitized_metadata'=>$sanitized,'removed_metadata_keys'=>$removed,'warnings'=>$warnings,'upload_allowed'=>$warnings===[],'client_redaction_preview_required'=>$warnings!==[]];
    }

    /** @param list<array{index:int,sha256:string,size:int}> $chunks @return array<string,mixed> */
    public function resumableUpload(string $uploadId,int $expectedSize,array $chunks,?string $finalSha256,bool $scanPassed): array
    {
        if (trim($uploadId)==='' || $expectedSize<1) throw new InvalidArgumentException('Resumable upload ID and expected size are required.');
        $size=0;$expectedIndex=0;$seen=[];
        foreach ($chunks as $chunk) {
            $index=$chunk['index'] ?? null;$hash=$chunk['sha256'] ?? null;$chunkSize=$chunk['size'] ?? null;
            if (!is_int($index)||$index!==$expectedIndex||!is_string($hash)||preg_match('/^[a-f0-9]{64}$/',$hash)!==1||!is_int($chunkSize)||$chunkSize<1) throw new InvalidArgumentException('Malformed or non-contiguous upload chunk.');
            if (isset($seen[$hash])) throw new InvalidArgumentException('Duplicate chunk hash detected.');
            $seen[$hash]=true;$size+=$chunkSize;++$expectedIndex;
        }
        $complete=$size===$expectedSize && $finalSha256!==null && preg_match('/^[a-f0-9]{64}$/',$finalSha256)===1;
        return ['feature_id'=>'CF02-FUT-014','upload_id'=>$uploadId,'received_size'=>$size,'expected_size'=>$expectedSize,'chunk_count'=>count($chunks),'complete'=>$complete,'scan_passed'=>$scanPassed,'evidence_accessible'=>$complete && $scanPassed,'final_sha256'=>$finalSha256];
    }
}
