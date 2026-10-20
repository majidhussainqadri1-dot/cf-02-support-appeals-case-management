<?php

declare(strict_types=1);

/** Bounded security regressions for two proven source-level defects.
 * Requires no WordPress installation. Real runtime integration is separately tested.
 */
if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        public function __construct(private array $headers = [], private array $params = []) {}
        public function get_header(string $name): string { return (string) ($this->headers[strtolower($name)] ?? ''); }
        public function get_param(string $name): mixed { return $this->params[$name] ?? null; }
    }
}
require_once dirname(__DIR__) . '/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__) . '/src');

use Sabri\CF02\Infrastructure\WordPress\RequestGuard;

$failed = [];
$test = static function (string $name, callable $fn) use (&$failed): void {
    try { $fn(); fwrite(STDOUT, "PASS $name\n"); }
    catch (Throwable $e) { $failed[] = $name . ': ' . $e->getMessage(); fwrite(STDERR, "FAIL $name: " . $e->getMessage() . "\n"); }
};
$mustReject = static function (callable $fn): void {
    try { $fn(); }
    catch (RuntimeException) { return; }
    throw new RuntimeException('Invalid input unexpectedly accepted.');
};
$req = static fn(array $headers=[],array $params=[]): WP_REST_Request => new WP_REST_Request(
    array_change_key_case($headers, CASE_LOWER), $params
);

$test('Exact dotted purpose is not silently rewritten', static function () use ($req): void {
    if (RequestGuard::purpose($req(['X-CF02-Purpose'=>'appeal.review'])) !== 'appeal.review') {
        throw new RuntimeException('Dotted purpose was altered.');
    }
});
$test('Exact hyphenated purpose is preserved', static function () use ($req): void {
    if (RequestGuard::purpose($req(['X-CF02-Purpose'=>'case-recovery'])) !== 'case-recovery') {
        throw new RuntimeException('Hyphenated purpose was altered.');
    }
});
$test('Header takes precedence over body purpose', static function () use ($req): void {
    if (RequestGuard::purpose($req(['X-CF02-Purpose'=>'case.support'], ['purpose'=>'appeal.review'])) !== 'case.support') {
        throw new RuntimeException('Purpose priority mismatch.');
    }
});
$test('Purpose fallback uses unmodified body value', static function () use ($req): void {
    if (RequestGuard::purpose($req([], ['purpose'=>'privacy.rights'])) !== 'privacy.rights') {
        throw new RuntimeException('Purpose fallback changed.');
    }
});
foreach (['case support','case/support','Case.support',"case.\r\nsupport",'ca','x'.str_repeat('a',64)] as $bad) {
    $test('Malformed purpose rejected: ' . bin2hex(substr($bad,0,6)), static function () use ($req,$mustReject,$bad): void {
        $mustReject(static fn()=>RequestGuard::purpose($req(['X-CF02-Purpose'=>$bad])));
    });
}
$test('Blank optional purpose remains blank', static function () use ($req): void {
    if (RequestGuard::purpose($req(),false) !== '') throw new RuntimeException('Optional purpose must be empty.');
});
$test('Maximum supported record ETag accepted exactly', static function () use ($req): void {
    if (RequestGuard::expectedVersion($req(['If-Match'=>'"'.(string)PHP_INT_MAX.'"'])) !== PHP_INT_MAX) {
        throw new RuntimeException('Maximum supported ETag rejected.');
    }
});
$test('Unquoted strong record ETag is accepted', static function () use ($req): void {
    if (RequestGuard::expectedVersion($req(['If-Match'=>'12345'])) !== 12345) {
        throw new RuntimeException('Valid unquoted ETag rejected.');
    }
});
$test('Record version can come from exact fallback parameter', static function () use ($req): void {
    if (RequestGuard::expectedVersion($req([],['record_version'=>'99'])) !== 99) {
        throw new RuntimeException('Valid version fallback rejected.');
    }
});
foreach ([str_repeat('9',19), '999999999999999999999999', (string)PHP_INT_MAX.'0','W/"1"','0','-1','01','"0001"','1x'] as $bad) {
    $test('Unsupported If-Match version rejected: '.substr($bad,0,20), static function () use ($req,$mustReject,$bad): void {
        $mustReject(static fn()=>RequestGuard::expectedVersion($req(['If-Match'=>$bad])));
    });
}
$test('Out-of-range record_version parameter fails instead of clamping', static function () use ($req,$mustReject): void {
    $mustReject(static fn()=>RequestGuard::expectedVersion($req([],['record_version'=>str_repeat('9',22)])));
});
$test('Strong idempotency keys still validate', static function () use ($req): void {
    if (RequestGuard::idempotencyKey($req(['Idempotency-Key'=>'request-abc-123456789'])) !== 'request-abc-123456789') {
        throw new RuntimeException('Valid idempotency key rejected.');
    }
});
$test('Short idempotency key is rejected', static function () use ($req,$mustReject): void {
    $mustReject(static fn()=>RequestGuard::idempotencyKey($req(['Idempotency-Key'=>'short'])));
});
$test('Repair audit insert failure cannot silently return success', static function (): void {
    $src = (string) file_get_contents(dirname(__DIR__).'/src/Infrastructure/WordPress/RepairService.php');
    $insert = strpos($src, '$inserted = $wpdb->insert(');
    $deny = strpos($src, "if ($"."inserted !== 1)");
    $success = strpos($src, "update_option('cf02_last_repair_evidence'");
    if ($insert===false||$deny===false||$success===false||!($insert<$deny&&$deny<$success)
        ||!str_contains($src,'Repair audit ledger persistence failed')) {
        throw new RuntimeException('Repair audit fail-closed contract or ordering is missing.');
    }
});
$test('Repair audit requires serializable outcome', static function (): void {
    $src = (string) file_get_contents(dirname(__DIR__).'/src/Infrastructure/WordPress/RepairService.php');
    if (!str_contains($src, "if (!is_string($"."json) || $"."json === '')")) {
        throw new RuntimeException('Serialized audit evidence guard is absent.');
    }
});
if ($failed !== []) exit(1);
fwrite(STDOUT, "RequestGuard and repair evidence security regressions passed.\n");
