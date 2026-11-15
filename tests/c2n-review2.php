<?php

declare(strict_types=1);
ini_set('assert.exception', '1');
assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_EXCEPTION, 1);
$root = dirname(__DIR__);
$source = (string) file_get_contents($root.'/src/Infrastructure/WordPress/CompleteFrontendSurfaces.php');
$guest = (string) file_get_contents($root.'/assets/js/cf02-guest.js');
$css = (string) file_get_contents($root.'/assets/css/cf02-frontend.css');
$manifest = json_decode((string)file_get_contents($root.'/release/manifest.json'),true,512,JSON_THROW_ON_ERROR);

assert(str_contains($source, "'cf02-guest-continuation'"));
assert(str_contains($source, "['cf02-complete-surfaces']"));
assert(str_contains($source, "if (!is_user_logged_in())"));
assert(str_contains($source, "GuestIntakePolicy::categories()"));
assert(str_contains($source, "data-cf02-guest-status"));
assert(str_contains($source, "role=\"status\""));
assert(str_contains($source, "aria-live=\"polite\""));
assert(str_contains($guest, 'const pending = readPending();'));
assert(str_contains($guest, "case_created !== false"));
assert(str_contains($guest, "if (!result.continuation_completed || !result.case)"));
assert(str_contains($guest, "panel.remove()"));
assert(str_contains($guest, "clearPending();"));
assert(str_contains($css, ".cf02-guest-resume"));
assert(str_contains($css, "color-scheme: dark"));
assert(str_contains($css, "--cf02-primary: var(--sabri-color-primary, #087A4E)"));
assert($manifest['plugin_version'] === '1.0.0-rc.8');
foreach ($manifest['external_acceptance_gates'] as $gate) assert(is_string($gate) && $gate !== '');
assert(str_contains((string)file_get_contents($root.'/docs/STAGING-ACCEPTANCE.md'), 'pending'));
fwrite(STDOUT,"CF-02 C2-N independent UX/release review passed.\n");
