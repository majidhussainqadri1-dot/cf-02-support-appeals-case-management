<?php

declare(strict_types=1);

namespace Sabri\CF02\Search;

use RuntimeException;

/** Opaque, signed, scoped and expiring keyset cursor. */
final class CursorCodec
{
    public function __construct(private readonly string $secret, private readonly ?int $clock = null)
    {
        if (strlen($secret) < 32) { throw new RuntimeException('Cursor-signing secret is unavailable.'); }
    }

    /** @param array<string,int|string> $position */
    public function encode(string $scope, array $position, int $ttlSeconds = 900): string
    {
        $this->assertScope($scope);
        $this->assertPosition($position);
        if ($ttlSeconds < 60 || $ttlSeconds > 3600) { throw new RuntimeException('Cursor lifetime is invalid.'); }
        $payload = ['v'=>1,'scope'=>$scope,'position'=>$position,'iat'=>$this->now(),'exp'=>$this->now()+$ttlSeconds];
        $body = self::base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $signature = self::base64UrlEncode(hash_hmac('sha256', $body, $this->secret, true));
        return $body.'.'.$signature;
    }

    /** @return array<string,int|string>|null */
    public function decode(?string $cursor, string $scope): ?array
    {
        $this->assertScope($scope);
        if ($cursor === null || trim($cursor) === '') { return null; }
        if (strlen($cursor) > 4096 || preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $cursor) !== 1) {
            throw new RuntimeException('Pagination cursor is invalid.');
        }
        [$body,$signature] = explode('.', $cursor, 2);
        $expected = self::base64UrlEncode(hash_hmac('sha256', $body, $this->secret, true));
        if (!hash_equals($expected, $signature)) { throw new RuntimeException('Pagination cursor integrity check failed.'); }
        $payload = json_decode(self::base64UrlDecode($body), true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($payload) || ($payload['v'] ?? null) !== 1 || ($payload['scope'] ?? null) !== $scope
            || !is_int($payload['iat'] ?? null) || !is_int($payload['exp'] ?? null)
            || $payload['iat'] > $this->now()+30 || $payload['exp'] < $this->now() || $payload['exp']-$payload['iat'] > 3600
            || !is_array($payload['position'] ?? null)) {
            throw new RuntimeException('Pagination cursor is expired or out of scope.');
        }
        /** @var array<string,int|string> $position */
        $position = $payload['position'];
        $this->assertPosition($position);
        return $position;
    }

    private function now(): int { return $this->clock ?? time(); }
    private function assertScope(string $scope): void
    {
        if (preg_match('/^[a-z][a-z0-9_.:-]{2,191}$/', $scope) !== 1) { throw new RuntimeException('Cursor scope is invalid.'); }
    }
    /** @param array<string,int|string> $position */
    private function assertPosition(array $position): void
    {
        if ($position === [] || count($position) > 8) { throw new RuntimeException('Cursor position is invalid.'); }
        foreach ($position as $key=>$value) {
            if (!is_string($key) || preg_match('/^[a-z][a-z0-9_]{0,31}$/', $key) !== 1 || (!is_int($value) && !is_string($value)) || is_string($value) && strlen($value)>191) {
                throw new RuntimeException('Cursor position is invalid.');
            }
        }
    }
    private static function base64UrlEncode(string $value): string { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); }
    private static function base64UrlDecode(string $value): string
    {
        $padding = (4 - strlen($value)%4)%4;
        $decoded = base64_decode(strtr($value.str_repeat('=', $padding), '-_', '+/'), true);
        if ($decoded === false) { throw new RuntimeException('Pagination cursor encoding is invalid.'); }
        return $decoded;
    }
}
