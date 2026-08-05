<?php
declare(strict_types=1);
ini_set('assert.exception','1');assert_options(ASSERT_ACTIVE,1);assert_options(ASSERT_EXCEPTION,1);
$root=dirname(__DIR__);$m=json_decode((string)file_get_contents($root.'/release/manifest.json'),true,512,JSON_THROW_ON_ERROR);
assert($m['plugin_version']==='1.0.0-rc.6');assert($m['database_schema_version']==='1.3.0');assert($m['contract_version']==='1.1.0');
assert(is_file($root.'/'.$m['traceability_manifest']));assert(is_file($root.'/'.$m['release_identity_verifier']));assert(is_file($root.'/'.$m['traceability_verifier']));
$release=(string)file_get_contents($root.'/docs/RELEASE-PROCESS.md');assert(str_contains($release,'Canonical identity source: `release/manifest.json`'));
$staging=(string)file_get_contents($root.'/docs/STAGING-ACCEPTANCE.md');assert(str_contains($staging,'RC6 artifact provenance source SHA'));assert(str_contains($staging,'RC6 `SHA256SUMS`'));
fwrite(STDOUT,"C2-L Review 1 passed: release identity and staging evidence drift corrected.\n");
