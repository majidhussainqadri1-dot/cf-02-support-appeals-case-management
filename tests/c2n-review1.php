<?php

declare(strict_types=1);
ini_set('assert.exception', '1');
assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_EXCEPTION, 1);
$root = dirname(__DIR__);
$js = (string) file_get_contents($root.'/assets/js/cf02-guest.js');
$php = (string) file_get_contents($root.'/src/Infrastructure/WordPress/CompleteFrontendSurfaces.php');
$backend = (string) file_get_contents($root.'/src/Infrastructure/WordPress/GuestIntakeController.php');

assert(str_contains($js, "window.sessionStorage.setItem(STORAGE_KEY"));
assert(str_contains($js, "window.sessionStorage.removeItem(STORAGE_KEY"));
assert(str_contains($js, "expires <= Date.now()") || str_contains($js, "Date.now() >= pending.expires"));
assert(str_contains($js, "'X-WP-Nonce'"));
assert(str_contains($js, "credentials: 'same-origin'"));
assert(str_contains($js, "cache: 'no-store'"));
assert(str_contains($js, 'status.textContent ='));
assert(!preg_match('/\\.localStorage\\s*\\./', $js));
assert(!preg_match('/\\.innerHTML\\s*=/', $js));
assert(!preg_match('/document\\.cookie/', $js));
assert(!preg_match('/location\\.(?:href|search)\\s*=.*token/', $js));
assert(str_contains($php, "esc_url(wp_login_url(home_url('/support')))"));
assert(str_contains($php, "esc_attr__('"));
assert(str_contains($backend, "GuestIntakePolicy::normalize("));
assert(str_contains($backend, "new \\WP_REST_Response(["));
assert(str_contains($backend, "'case_created' => false"));
assert(str_contains($backend, "hasCapability('case.create')"));
fwrite(STDOUT, "CF-02 C2-N first security and privacy review passed.\n");
