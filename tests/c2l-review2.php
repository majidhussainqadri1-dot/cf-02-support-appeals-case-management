<?php
declare(strict_types=1);
ini_set('assert.exception','1');assert_options(ASSERT_ACTIVE,1);assert_options(ASSERT_EXCEPTION,1);
$root=dirname(__DIR__);$t=json_decode((string)file_get_contents($root.'/release/traceability.json'),true,512,JSON_THROW_ON_ERROR);
assert($t['external_acceptance_gates_status']==='pending-external');
foreach($t['requirements'] as $r){assert($r['staging_evidence_status']==='pending-external');}
$prod=(string)file_get_contents($root.'/docs/PRODUCTION-READINESS.md');foreach(['Real companion contracts','Real providers','Security deployment tests','Accessibility/device','Performance/resilience','Migration','Backup/restore','Staffing/operations','Observation window','Founder acceptance'] as $gate){assert(str_contains($prod,$gate));assert(str_contains($prod,'not-started'));}
$manifest=json_decode((string)file_get_contents($root.'/release/manifest.json'),true,512,JSON_THROW_ON_ERROR);assert($manifest['release_status']==='three-plan-harmonized-candidate-not-staging-accepted');
fwrite(STDOUT,"C2-L fresh adversarial Review 2 passed: no external evidence was converted into a code pass.\n");
