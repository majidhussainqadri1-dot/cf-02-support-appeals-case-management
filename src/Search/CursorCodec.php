<?php

declare(strict_types=1);

namespace Sabri\CF02\Search;

use RuntimeException;

/** Opaque, signed and expiring keyset cursor. */
final class CursorCodec
{
    public function __construct(private readonly string $secret)
    {
        if (strlen($secret) < 32) {
            throw new RuntimeException('Cursor-signing secret is unavailable.');
        }
    }

    /** @param array<string,int|string> $position */
    public function encode(string $scope, array $position, int $ttlSeconds = 900): string
    {
        if ($ttlSeconds < 60 || $ttlSeconds > 3600 || preg_match('/^[a-z][a-z0-9_.:-]{2,191}$/', $scope) !== 1) {
            throw new RuntimeException('Cursor scope or lifetime is invalid.');
        }
        $payload = ['v' => 1, 'scope' => $scope, 'position' => $position, 'exp' => time() + $ttlSeconds];
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $body = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', $body, $this->secret, true)), '+/', '-_'), '=');
        return $body . '.' . $signature;
    }

    /** @return array<string,int|string>|null */
    public function decode(?string $cursor, string $scope): ?array
    {
        if ($cursor === null || trim($cursor) === '') {
            return null;
        }
        $parts = explode('.', $cursor, 2);
        if (count($parts) !== 2) {
            throw new RuntimeException('Pagination cursor is invalid.');
        }
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $parts[0], $this->secret, true)), '+/', '-_'), '=');
        if (!hash_equals($expected, $parts[1])) {
            throw new RuntimeException('Pagination cursor integrity check failed.');
        }
        $raw = base64_decode(strtr($parts[0], '-_', '+/'), true);
        $payload = $raw === false ? null : json_decode($raw, true);
        if (!is_array($payload) || ($payload['v'] ?? null) !== 1 || ($payload['scope'] ?? null) !== $scope
            || !is_int($payload['exp'] ?? null) || $payload['exp'] < time() || !is_array($payload['position'] ?? null)) {
            throw new RuntimeException('Pagination cursor is expired or out of scope.');
        }
        /** @var array<string,int|string> $position */
        $position = $payload['position'];
        return $position;
    }
}
