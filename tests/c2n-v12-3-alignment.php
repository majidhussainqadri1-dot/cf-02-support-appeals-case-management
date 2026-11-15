<?php

declare(strict_types=1);

ini_set('assert.exception', '1');
assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_EXCEPTION, 1);
require_once dirname(__DIR__) . '/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__) . '/src');

use Sabri\CF02\Intake\GuestIntakePolicy;
use Sabri\CF02\Intake\GuestContinuationToken;

$root = dirname(__DIR__);
$failures = [];
$test = static function (string $label, callable $run) use (&$failures): void {
    try { $run(); fwrite(STDOUT, "PASS {$label}\n"); }
    catch (Throwable $error) { $failures[] = $label . ': ' . $error->getMessage(); fwrite(STDERR, "FAIL {$label}: {$error->getMessage()}\n"); }
};

$test('V12.3 guest categories are explicitly restricted and require a non-sensitive payload', static function (): void {
    assert(GuestIntakePolicy::categories() === ['publishing', 'learning_access', 'media_pdf', 'accessibility', 'technical']);
    $safe = GuestIntakePolicy::normalize(['category'=>'technical','subject'=>'Page loading problem','description'=>'The support page is slow.','impact'=>'single_action','urgency'=>'normal','locale'=>'ur-PK']);
    assert($safe['category'] === 'technical');
    $denied = false;
    try { GuestIntakePolicy::normalize(['category'=>'clinical','subject'=>'Private medical issue']); }
    catch (InvalidArgumentException) { $denied = true; }
    assert($denied, 'Sensitive clinical categories must not enter anonymous pre-intake.');
});

$test('V12.3 guest continuation is confidential, short lived and tamper resistant', static function (): void {
    $key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    $service = new GuestContinuationToken($key);
    $now = new DateTimeImmutable('2026-11-15T12:00:00+00:00');
    $token = $service->issue(['category'=>'technical','subject'=>'Page loading problem','description'=>'No private data'], $now, 600);
    assert(str_starts_with($token, 'CF02G1.'));
    assert(!str_contains($token, 'Page loading problem'));
    assert($service->verify($token, $now)['intake']['category'] === 'technical');
    $expired = false;
    try { $service->verify($token, $now->modify('+11 minutes')); }
    catch (InvalidArgumentException) { $expired = true; }
    assert($expired, 'Expired token must be rejected.');
    $middle = intdiv(strlen($token), 2);
    $tampered = substr_replace($token, $token[$middle] === 'a' ? 'b' : 'a', $middle, 1);
    $rejected = false;
    try { $service->verify($tampered, $now); }
    catch (InvalidArgumentException) { $rejected = true; }
    assert($rejected, 'Tampered token must be rejected.');
});

$test('V12.3 public UI has a bounded pre-intake and a second authenticated confirmation', static function () use ($root): void {
    $php = file_get_contents($root . '/src/Infrastructure/WordPress/CompleteFrontendSurfaces.php');
    $js = file_get_contents($root . '/assets/js/cf02-guest.js');
    $controller = file_get_contents($root . '/src/Infrastructure/WordPress/GuestIntakeController.php');
    assert(str_contains($php, 'GuestIntakePolicy::categories()'));
    assert(str_contains($php, 'return $public . self::guestPreIntake();'));
    assert(str_contains($php, 'data-cf02-guest'));
    assert(str_contains($php, 'data-guest-resume-title'));
    assert(str_contains($php, 'maxlength="3000"'));
    assert(str_contains($controller, "'case_created' => false"));
    assert(str_contains($controller, 'authenticatedPermission'));
    assert(str_contains($controller, "hasCapability('case.create')"));
    assert(str_contains($js, "'/guest/continue'"));
    assert(str_contains($js, "continuation_completed"));
    assert(str_contains($js, "clearPending();"));
});

$test('V12.3 visual tokens match central File 25 specification and support dark surfaces', static function () use ($root): void {
    $css = file_get_contents($root . '/assets/css/cf02-frontend.css');
    foreach (['#087A4E', '#065C3B', '#E7F5EE', '#D6E1DA', '#6B8476', 'prefers-color-scheme: dark', 'data-theme="dark"', 'prefers-reduced-motion: reduce', 'forced-colors: active'] as $token) {
        assert(str_contains($css, $token), $token . ' missing');
    }
    assert(!str_contains($css, '#198754'), 'Legacy green must not be the CF-02 fallback.');
});

$test('Release identity uses a new RC and canonical 1.3.0 contract', static function () use ($root): void {
    $manifest = json_decode(file_get_contents($root . '/release/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    assert($manifest['plugin_version'] === '1.0.0-rc.8');
    assert($manifest['contract_version'] === '1.3.0');
    assert(str_contains(file_get_contents($root . '/docs/PRODUCTION-READINESS.md'), 'Contract: `1.3.0`'));
});
if ($failures !== []) exit(1);
fwrite(STDOUT, "CF-02 V12.3 alignment tests passed.\n");
