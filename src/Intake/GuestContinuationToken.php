<?php

declare(strict_types=1);

namespace Sabri\CF02\Intake;

use DateTimeImmutable;
use InvalidArgumentException;

final class GuestContinuationToken
{
    public function __construct(private readonly string $key)
    {
        if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new InvalidArgumentException('Guest continuation key length is invalid.');
        }
    }

    /** @param array<string,mixed> $safeIntake */
    public function issue(array $safeIntake, DateTimeImmutable $now, int $ttlSeconds = 600): string
    {
        if ($ttlSeconds < 60 || $ttlSeconds > 900) {
            throw new InvalidArgumentException('Guest continuation lifetime must be 60 to 900 seconds.');
        }
        $payload = [
            'version' => 1,
            'token_id' => bin2hex(random_bytes(16)),
            'issued_at' => $now->getTimestamp(),
            'expires_at' => $now->getTimestamp() + $ttlSeconds,
            'intake' => $safeIntake,
        ];
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox(
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $nonce,
            $this->key
        );
        return 'CF02G1.' . self::base64UrlEncode($nonce . $ciphertext);
    }

    /** @return array{version:int,token_id:string,issued_at:int,expires_at:int,intake:array<string,mixed>} */
    public function verify(string $token, DateTimeImmutable $now): array
    {
        if (!str_starts_with($token, 'CF02G1.')) {
            throw new InvalidArgumentException('Guest continuation token version is invalid.');
        }
        $decoded = self::base64UrlDecode(substr($token, 7));
        if (strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new InvalidArgumentException('Guest continuation token is malformed.');
        }
        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);
        if ($plaintext === false) {
            throw new InvalidArgumentException('Guest continuation token authentication failed.');
        }
        $payload = json_decode($plaintext, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($payload)
            || ($payload['version'] ?? null) !== 1
            || !is_string($payload['token_id'] ?? null)
            || preg_match('/^[a-f0-9]{32}$/', $payload['token_id']) !== 1
            || !is_int($payload['issued_at'] ?? null)
            || !is_int($payload['expires_at'] ?? null)
            || !is_array($payload['intake'] ?? null)) {
            throw new InvalidArgumentException('Guest continuation token payload is invalid.');
        }
        if ($payload['expires_at'] <= $payload['issued_at']
            || $payload['expires_at'] - $payload['issued_at'] > 900
            || $now->getTimestamp() < $payload['issued_at'] - 60
            || $now->getTimestamp() >= $payload['expires_at']) {
            throw new InvalidArgumentException('Guest continuation token is expired or outside its allowed time window.');
        }
        return $payload;
    }

    private static function base64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $encoded): string
    {
        if ($encoded === '' || preg_match('/^[A-Za-z0-9_-]+$/', $encoded) !== 1) {
            throw new InvalidArgumentException('Guest continuation token encoding is invalid.');
        }
        $padding = (4 - strlen($encoded) % 4) % 4;
        $decoded = base64_decode(strtr($encoded . str_repeat('=', $padding), '-_', '+/'), true);
        if ($decoded === false) {
            throw new InvalidArgumentException('Guest continuation token cannot be decoded.');
        }
        return $decoded;
    }
}
