<?php

declare(strict_types=1);

namespace Sabri\CF02\Future;

use DateTimeImmutable;
use InvalidArgumentException;

final class IntegrationSecurityIntelligence
{
    public function __construct(private readonly string $signingKey)
    {
        if (strlen($signingKey)<32) throw new InvalidArgumentException('Future integration signing key must be at least 32 bytes.');
    }

    /** @return array<string,mixed> */
    public function issueSecureDeepLink(string $purpose,string $opaqueReference,string $relativePath,DateTimeImmutable $now,int $ttlSeconds=600): array
    {
        if (!in_array($purpose,['case_receipt','case_status','appeal','support_handoff'],true) || preg_match('/^[A-Za-z0-9_-]{12,128}$/',$opaqueReference)!==1 || $ttlSeconds<60 || $ttlSeconds>3600) throw new InvalidArgumentException('Secure deep-link request is invalid.');
        if (!str_starts_with($relativePath,'/') || str_starts_with($relativePath,'//') || preg_match('#^[a-z]+://#i',$relativePath)===1) throw new InvalidArgumentException('Only local relative destinations are permitted.');
        $expires=$now->getTimestamp()+$ttlSeconds;
        $payload=$purpose.'|'.$opaqueReference.'|'.$relativePath.'|'.$expires;
        $sig=rtrim(strtr(base64_encode(hash_hmac('sha256',$payload,$this->signingKey,true)),'+/','-_'),'=');
        return ['feature_id'=>'CF02-FUT-021','token'=>'CF02DL1.'.$expires.'.'.$opaqueReference.'.'.$sig,'relative_path'=>$relativePath,'expires_at'=>(new DateTimeImmutable('@'.$expires))->format(DATE_ATOM),'raw_case_id_exposed'=>false,'qr_payload_allowed'=>true];
    }

    public function verifySecureDeepLink(string $purpose,string $relativePath,string $token,DateTimeImmutable $now): bool
    {
        $parts=explode('.',$token);
        if (count($parts)!==4 || $parts[0]!=='CF02DL1' || !ctype_digit($parts[1]) || (int)$parts[1]<$now->getTimestamp()) return false;
        [$prefix,$expires,$reference,$sig]=$parts;
        if (!str_starts_with($relativePath,'/') || str_starts_with($relativePath,'//')) return false;
        $payload=$purpose.'|'.$reference.'|'.$relativePath.'|'.$expires;
        $expected=rtrim(strtr(base64_encode(hash_hmac('sha256',$payload,$this->signingKey,true)),'+/','-_'),'=');
        return hash_equals($expected,$sig);
    }

    /** @param list<string> $scopes @return array<string,mixed> */
    public function institutionalApiPolicy(string $clientId,array $scopes,string $idempotencyKey,string $payload,DateTimeImmutable $issuedAt,string $nonce): array
    {
        if (preg_match('/^[a-zA-Z0-9._-]{3,80}$/',$clientId)!==1 || preg_match('/^[A-Za-z0-9_-]{16,128}$/',$idempotencyKey)!==1 || preg_match('/^[A-Za-z0-9_-]{16,128}$/',$nonce)!==1) throw new InvalidArgumentException('Institutional API identity/idempotency/nonce is invalid.');
        $allowed=['case.create','case.read','case.reply','case.status','appeal.create','incident.read'];
        foreach ($scopes as $scope) if (!in_array($scope,$allowed,true)) throw new InvalidArgumentException('Institutional API scope is not permitted.');
        $bodyHash=hash('sha256',$payload);
        $canonical=$clientId.'|'.implode(',',array_values(array_unique($scopes))).'|'.$idempotencyKey.'|'.$nonce.'|'.$issuedAt->getTimestamp().'|'.$bodyHash;
        $signature=hash_hmac('sha256',$canonical,$this->signingKey);
        return ['feature_id'=>'CF02-FUT-022','client_id'=>$clientId,'scopes'=>array_values(array_unique($scopes)),'idempotency_key'=>$idempotencyKey,'nonce'=>$nonce,'issued_at'=>$issuedAt->format(DATE_ATOM),'body_sha256'=>$bodyHash,'webhook_signature'=>$signature,'signature_algorithm'=>'HMAC-SHA256','replay_protection_required'=>true,'secret_rotation_required'=>true];
    }
}
