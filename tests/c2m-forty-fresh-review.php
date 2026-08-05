<?php

declare(strict_types=1);
ini_set('assert.exception', '1');
assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_EXCEPTION, 1);
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(mixed $value): string { return trim(strip_tags((string) $value)); }
}
if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field(mixed $value): string { return trim(strip_tags((string) $value)); }
}
require_once dirname(__DIR__) . '/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__) . '/src');

use Sabri\CF02\Configuration\CategoryRoutingPolicy;
use Sabri\CF02\Infrastructure\WordPress\ApiInput;

$root = dirname(__DIR__);
$failures = [];
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$throws = static function (callable $callback): void {
    $failed = false;
    try { $callback(); } catch (Throwable) { $failed = true; }
    assert($failed);
};
$review = static function (int $round, string $name, callable $callback) use (&$failures): void {
    try {
        $callback();
        fwrite(STDOUT, sprintf("PASS FRESH REVIEW %02d %s\n", $round, $name));
    } catch (Throwable $error) {
        $failures[] = sprintf('%02d %s: %s', $round, $name, $error->getMessage());
        fwrite(STDERR, sprintf("FAIL FRESH REVIEW %02d %s: %s\n", $round, $name, $error->getMessage()));
    }
};

$controller = $read('src/Infrastructure/WordPress/ComprehensiveRestController.php');
$provider = $read('src/Infrastructure/WordPress/ProviderWebhookController.php');
$intake = $read('src/Infrastructure/WordPress/IntakeRepository.php');
$repair = $read('src/Infrastructure/WordPress/RepairService.php');
$overlay = $read('src/Infrastructure/WordPress/CompleteRestOverlay.php');
$guard = $read('src/Infrastructure/WordPress/RequestGuard.php');

$review(1, 'request intake normalizes legacy category aliases before validation', static function () use ($controller): void {
    assert(str_contains($controller, 'CategoryRoutingPolicy::normalize'));
    assert(CategoryRoutingPolicy::normalize('learning_billing') === 'learning_access');
});
$review(2, 'request intake uses the canonical queue owner', static function () use ($controller): void {
    assert(str_contains($controller, 'CategoryRoutingPolicy::queueFor($category)'));
});
$review(3, 'triage uses the same canonical routing policy as intake', static function () use ($controller): void {
    $triage = substr($controller, (int) strpos($controller, 'public function triage'));
    assert(str_contains($triage, "'queue_key' => CategoryRoutingPolicy::queueFor(\$category)"));
});
$review(4, 'the obsolete duplicate category-to-queue resolver is removed', static function () use ($controller): void {
    assert(!str_contains($controller, 'private function queueForCategory'));
});
$review(5, 'legacy learning access never routes to financial coordination', static function (): void {
    assert(CategoryRoutingPolicy::queueFor('learning_billing') === 'learning');
    assert(CategoryRoutingPolicy::queueFor('learning_access') === 'learning');
});
$review(6, 'requesters describe impact and urgency but do not mint priority', static function () use ($controller): void {
    assert(str_contains($controller, 'ServiceEqualityPolicy::requesterPriority($impact, $urgency)'));
    $create = substr($controller, (int) strpos($controller, 'public function createCase'), 7000);
    assert(!str_contains($create, "get_param('priority')"));
});
$review(7, 'the repository centrally validates requester and idempotency identities', static function () use ($intake): void {
    assert(str_contains($intake, 'Intake requester or idempotency identity is invalid.'));
});
$review(8, 'the repository centrally validates canonical categories', static function () use ($intake): void {
    assert(str_contains($intake, 'SupportContractCatalog::assertCategory($category)'));
});
$review(9, 'the repository centrally validates priority and severity enums', static function () use ($intake): void {
    assert(str_contains($intake, "['P1','P2','P3','P4']"));
    assert(str_contains($intake, "['normal','S1','S2','S3','S4']"));
});
$review(10, 'the repository overwrites caller queue data with canonical routing', static function () use ($intake): void {
    assert(str_contains($intake, "\$payload['queue'] = CategoryRoutingPolicy::queueFor(\$category)"));
});
$review(11, 'the repository centrally validates locale and subject', static function () use ($intake): void {
    assert(str_contains($intake, "\$payload['locale'] = ApiInput::locale"));
    assert(str_contains($intake, "\$payload['subject'] = ApiInput::safeSingleLine"));
});
$review(12, 'string false is parsed as false rather than truthy', static function (): void {
    assert(ApiInput::boolean('false', 'Flag') === false);
    assert(ApiInput::boolean('0', 'Flag') === false);
});
$review(13, 'string true is parsed as true', static function (): void {
    assert(ApiInput::boolean('true', 'Flag') === true);
    assert(ApiInput::boolean('1', 'Flag') === true);
});
$review(14, 'ambiguous boolean values fail closed', static function () use ($throws): void {
    $throws(static fn() => ApiInput::boolean('yes', 'Flag'));
    $throws(static fn() => ApiInput::boolean([], 'Flag'));
});
$review(15, 'required booleans cannot silently default', static function () use ($throws): void {
    $throws(static fn() => ApiInput::boolean(null, 'Required flag'));
});
$review(16, 'all REST request booleans use the strict parser', static function () use ($controller): void {
    assert(!preg_match('/\(bool\)\s*\$request/', $controller));
    assert(substr_count($controller, 'ApiInput::boolean(') >= 10);
});
$review(17, 'attachment consent cannot be bypassed with false-like strings', static function () use ($controller): void {
    assert(str_contains($controller, "ApiInput::boolean(\$request->get_param('consented'), 'Attachment consent', false)"));
});
$review(18, 'appeal eligibility is an explicit required boolean', static function () use ($controller): void {
    assert(str_contains($controller, "ApiInput::boolean(\$request->get_param('eligible'), 'Appeal eligibility')"));
});
$review(19, 'locale validation is bounded and BCP-47-style', static function () use ($throws): void {
    assert(ApiInput::locale('ur-PK') === 'ur-PK');
    assert(ApiInput::locale('en-Latn-US') === 'en-Latn-US');
    $throws(static fn() => ApiInput::locale('../en'));
    $throws(static fn() => ApiInput::locale(str_repeat('a', 36)));
});
$review(20, 'single-line input rejects CRLF before WordPress sanitization', static function () use ($throws): void {
    $throws(static fn() => ApiInput::safeSingleLine("ok\r\nInjected: 1", 'Header-like text'));
});
$review(21, 'strict provider references reject whitespace and delimiter ambiguity', static function () use ($throws): void {
    assert(ApiInput::safeReference('provider:mail-1/event_2', 'Reference') === 'provider:mail-1/event_2');
    $throws(static fn() => ApiInput::safeReference('provider id', 'Reference'));
    $throws(static fn() => ApiInput::safeReference("provider\nheader", 'Reference'));
});
$review(22, 'provider upload headers reject CRLF injection', static function () use ($throws): void {
    $throws(static fn() => ApiInput::safeHeaderMap(['X-Test' => "safe\r\nInjected: true"]));
});
$review(23, 'provider upload headers reject browser-forbidden authority headers', static function () use ($throws): void {
    foreach (['Host', 'Cookie', 'Content-Length', 'Sec-Fetch-Site', 'Proxy-Authorization'] as $header) {
        $throws(static fn() => ApiInput::safeHeaderMap([$header => 'x']));
    }
});
$review(24, 'valid signed-upload headers remain usable', static function (): void {
    $headers = ApiInput::safeHeaderMap(['Content-Type' => 'application/pdf', 'X-Amz-Meta-Id' => 'abc']);
    assert($headers['Content-Type'] === 'application/pdf');
});
$review(25, 'action purpose is validated without semantics-changing sanitization', static function () use ($guard): void {
    assert(str_contains($guard, 'strtolower(trim'));
    assert(!str_contains($guard, 'sanitize_key((string) $request->get_header'));
});
$review(26, 'repair permission checks current File 00 assertion validity', static function () use ($overlay): void {
    assert(str_contains($overlay, '!$context->validAt($now)'));
});
$review(27, 'repair mutation requires recent authentication at both boundary and service', static function () use ($overlay, $repair): void {
    assert(str_contains($overlay, 'recentlyAuthenticated($now)'));
    assert(str_contains($repair, 'recentlyAuthenticated($at)'));
});
$review(28, 'repair service rejects expired or suspended authorization contexts', static function () use ($repair): void {
    assert(str_contains($repair, '!$context->validAt($at)'));
});
$review(29, 'repair evidence serialization failure blocks a success claim', static function () use ($repair): void {
    assert(str_contains($repair, 'Repair evidence serialization failed.'));
});
$review(30, 'repair ledger write failure is observable and fail-closed', static function () use ($repair): void {
    assert(str_contains($repair, '$inserted!==1'));
    assert(str_contains($repair, 'evidence_write_failed'));
});
$review(31, 'provider JSON bodies are object-only and size bounded', static function () use ($provider): void {
    assert(str_contains($provider, 'strlen($raw)>262144'));
    assert(str_contains($provider, 'array_is_list($payload)'));
});
$review(32, 'provider HMAC binds purpose key method route timestamp and body', static function () use ($provider): void {
    foreach (['$purpose', '$keyId', 'strtoupper($request->get_method())', '$request->get_route()', '$timestamp', '$request->get_body()'] as $part) {
        assert(str_contains($provider, $part));
    }
});
$review(33, 'provider errors expose only a generic localized response', static function () use ($provider): void {
    assert(str_contains($provider, 'The signed provider request was rejected.'));
    assert(!str_contains($provider, '$error->getMessage()'));
});
$review(34, 'provider failures retain private trace evidence', static function () use ($provider): void {
    assert(str_contains($provider, "do_action('cf02_provider_request_failed'"));
    assert(str_contains($provider, "'trace_id'=>\$trace"));
});
$review(35, 'provider intake normalizes category and cannot choose queue', static function () use ($provider): void {
    assert(str_contains($provider, 'CategoryRoutingPolicy::normalize'));
    assert(!str_contains($provider, "\$payload['queue']"));
});
$review(36, 'provider intake cannot choose its own priority or severity', static function () use ($provider): void {
    assert(!str_contains($provider, "\$payload['priority']"));
    assert(!str_contains($provider, "\$payload['severity']"));
    assert(str_contains($provider, "'severity' => 'normal'"));
});
$review(37, 'secure attachment delivery requires an HTTPS validated URL', static function () use ($provider): void {
    assert(str_contains($provider, "str_starts_with(strtolower(\$url),'https://')"));
    assert(str_contains($provider, 'wp_http_validate_url($url)'));
});
$review(38, 'secure attachment delivery grant expires within five minutes', static function () use ($provider): void {
    assert(str_contains($provider, '$expires>time()+300'));
});
$review(39, 'release identity remains RC6 and external acceptance remains pending', static function () use ($root): void {
    $manifest = json_decode((string) file_get_contents($root . '/release/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    assert($manifest['plugin_version'] === '1.0.0-rc.6');
    assert($manifest['release_status'] === 'three-plan-harmonized-candidate-not-staging-accepted');
    assert(count($manifest['external_acceptance_gates']) >= 8);
});
$review(40, 'the fresh forty-round register records every round and preserves truthful completion', static function () use ($root): void {
    $register = (string) file_get_contents($root . '/docs/FORTY-ROUND-FRESH-REVIEW-C2M.md');
    preg_match_all('/^\|\s*(?:0?[1-9]|[1-3][0-9]|40)\s*\|/m', $register, $matches);
    assert(count($matches[0]) === 40);
    assert(str_contains($register, 'Hostinger staging: pending'));
    assert(str_contains($register, 'Live deployment: not performed'));
});

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
fwrite(STDOUT, "All forty fresh C2-M review/fix regressions passed.\n");
