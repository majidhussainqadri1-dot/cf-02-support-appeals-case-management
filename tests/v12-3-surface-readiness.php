<?php

declare(strict_types=1);

ini_set('assert.exception', '1');
assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_EXCEPTION, 1);

$root = dirname(__DIR__);
$php = (string) file_get_contents($root . '/src/Infrastructure/WordPress/CompleteFrontendSurfaces.php');
$js = (string) file_get_contents($root . '/assets/js/cf02-frontend.js');
$css = (string) file_get_contents($root . '/assets/css/cf02-frontend.css');
$contract = (string) file_get_contents($root . '/src/Contracts/SupportContractCatalog.php');
$manifest = json_decode((string) file_get_contents($root . '/release/manifest.json'), true, 512, JSON_THROW_ON_ERROR);

assert($manifest['contract_version'] === '1.3.0');
assert(str_contains($contract, "CONTRACT_VERSION = '1.3.0'"));
assert(str_contains($php, 'private static function guestIntake(): string'));
assert(str_contains($php, 'data-cf02-guest'));
assert(str_contains($php, 'wp_login_url('));
assert(str_contains($php, "rest_url('api/support/v1')"));
assert(str_contains($php, 'is_user_logged_in()'));
assert(str_contains($js, "guestStorageKey = 'cf02_guest_stepup_v1'"));
assert(str_contains($js, "'/guest/intake'"));
assert(str_contains($js, "'/guest/continue'"));
assert(str_contains($js, 'window.sessionStorage.removeItem(guestStorageKey)'));
assert(str_contains($js, 'Number.isFinite(saved.expires)'));
$guestController = (string) file_get_contents($root . '/src/Infrastructure/WordPress/GuestIntakeController.php');
assert(str_contains($php, 'data-cf02-guest-confirm'));
assert(str_contains($php, 'data-action="guest-convert"'));
assert(str_contains($php, 'data-action="guest-discard"'));
assert(str_contains($js, "const guestConfirm = root.querySelector('[data-cf02-guest-confirm]')"));
assert(str_contains($js, "confirm.addEventListener('click'"));
assert(str_contains($js, "discard.addEventListener('click'"));
assert(str_contains($guestController, "\$receivedAt = new DateTimeImmutable((string) \$result['case']['created_at']"));
assert(str_contains($guestController, "'received_at' => \$receivedAt->format(DATE_ATOM)"));
assert(str_contains($css, 'var(--sabri-color-primary, #087A4E)'));
assert(str_contains($css, 'var(--sabri-color-primary-strong, #065C3B)'));
assert(str_contains($css, '@media (prefers-color-scheme: dark)'));
foreach ([
    'docs/PRODUCTION-READINESS.md',
    'docs/RELEASE-PROCESS.md',
    'docs/FUTURE24-IMPLEMENTATION.md',
    'docs/CHANGE-CONTROL-C2M.md',
    'docs/COMPLETE-CODING-CANDIDATE.md',
] as $path) {
    $document = (string) file_get_contents($root . '/' . $path);
    assert(!str_contains($document, 'Contract: `1.1.0`'));
    assert(!str_contains($document, 'contract remains `1.1.0`'));
}
fwrite(STDOUT, "PASS V12.3 scoped source consistency and guest-step-up regression\n");
