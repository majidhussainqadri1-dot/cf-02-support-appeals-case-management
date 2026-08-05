<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use DateTimeImmutable;
use RuntimeException;
use Sabri\CF02\Authorization\PrincipalContext;

final class RequestGuard
{
    public static function requireCapability(PrincipalContext $context, DateTimeImmutable $now, string ...$capabilities): void
    {
        if (!$context->validAt($now) || !$context->hasAnyCapability(...$capabilities)) {
            throw new RuntimeException('The requested action is not authorized.');
        }
    }

    public static function requireRecentAuthentication(PrincipalContext $context, DateTimeImmutable $now): void
    {
        if (!$context->recentlyAuthenticated($now)) {
            throw new RuntimeException('Recent authentication is required.');
        }
    }

    public static function purpose(\WP_REST_Request $request, bool $required = true): string
    {
        $purpose = strtolower(trim((string) $request->get_header('X-CF02-Purpose')));
        if ($purpose === '') {
            $purpose = strtolower(trim((string) $request->get_param('purpose')));
        }
        if ($purpose === '') {
            if ($required) { throw new RuntimeException('A bounded action purpose is required.'); }
            return '';
        }
        if (preg_match('/^[a-z][a-z0-9_]{2,63}$/', $purpose) !== 1) {
            throw new RuntimeException('A bounded action purpose is required.');
        }
        return $purpose;
    }

    public static function idempotencyKey(\WP_REST_Request $request): string
    {
        $key = trim((string) $request->get_header('Idempotency-Key'));
        if ($key === '') {
            $key = trim((string) $request->get_param('idempotency_key'));
        }
        if (preg_match('/^[A-Za-z0-9._:-]{16,128}$/', $key) !== 1) {
            throw new RuntimeException('A valid idempotency key is required.');
        }
        return $key;
    }

    public static function expectedVersion(\WP_REST_Request $request): int
    {
        $header = trim((string) $request->get_header('If-Match'));
        if (str_starts_with(strtoupper($header), 'W/')) {
            throw new RuntimeException('A weak ETag cannot authorize a mutation.');
        }
        if ($header !== '') {
            if (preg_match('/^(?:"([1-9][0-9]{0,18})"|([1-9][0-9]{0,18}))$/', $header, $matches) !== 1) {
                throw new RuntimeException('A strong If-Match record version is required.');
            }
            $raw = $matches[1] !== '' ? $matches[1] : $matches[2];
        } else {
            $raw = (string) $request->get_param('record_version');
        }
        if (preg_match('/^[1-9][0-9]{0,18}$/', $raw) !== 1) {
            throw new RuntimeException('A strong If-Match record version is required.');
        }
        return (int) $raw;
    }

    public static function approvalReference(\WP_REST_Request $request): string
    {
        $value = trim((string) $request->get_header('X-CF02-Approval-Ref'));
        if ($value === '') {
            $value = trim((string) $request->get_param('approval_ref'));
        }
        if (preg_match('/^[A-Za-z0-9._:\/-]{8,191}$/', $value) !== 1) {
            throw new RuntimeException('A governed approval reference is required.');
        }
        return $value;
    }

    public static function traceId(): string
    {
        return 'tr_' . bin2hex(random_bytes(16));
    }
}
