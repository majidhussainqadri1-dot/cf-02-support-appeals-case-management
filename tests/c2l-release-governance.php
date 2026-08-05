<?php
declare(strict_types=1);
ini_set('assert.exception','1');assert_options(ASSERT_ACTIVE,1);assert_options(ASSERT_EXCEPTION,1);
$root=dirname(__DIR__);$failures=[];$test=static function(string $n,callable $c)use(&$failures):void{try{$c();fwrite(STDOUT,"PASS {$n}\n");}catch(Throwable $e){$failures[]=$n.': '.$e->getMessage();fwrite(STDERR,"FAIL {$n}: {$e->getMessage()}\n");}};
$m=json_decode((string)file_get_contents($root.'/release/manifest.json'),true,512,JSON_THROW_ON_ERROR);
$test('release identity is singular and current across operative documents',static function()use($root,$m):void{
    foreach(['docs/RELEASE-PROCESS.md','docs/STAGING-ACCEPTANCE.md','docs/PRODUCTION-READINESS.md','docs/CHANGE-CONTROL.md'] as $path){$s=(string)file_get_contents($root.'/'.$path);assert(str_contains($s,$m['plugin_version']));assert(str_contains($s,$m['database_schema_version']));assert(!str_contains($s,'1.0.0-rc.2'));assert(!str_contains($s,'1.0.0-rc.3'));assert(!str_contains($s,'1.0.0-rc.4'));assert(!str_contains($s,'1.0.0-rc.5'));}
});
$test('three plan machine traceability covers every mandatory CF02 requirement',static function()use($root):void{
    $t=json_decode((string)file_get_contents($root.'/release/traceability.json'),true,512,JSON_THROW_ON_ERROR);assert(count($t['requirements'])===34);foreach($t['requirements'] as $i=>$r){assert($r['id']===sprintf('CF02-FR-%03d',$i+1));foreach(['source','tests','migration','security_privacy'] as $field){assert($r[$field]!==[]);foreach($r[$field] as $path)assert(is_file($root.'/'.$path));}assert($r['staging_evidence_status']==='pending-external');}
});
$test('workflows derive current package identity from the manifest',static function()use($root):void{
    foreach(['release-candidate.yml','wordpress-smoke.yml','wordpress-runtime-smoke.yml'] as $name){$s=(string)file_get_contents($root.'/.github/workflows/'.$name);assert(str_contains($s,'Resolve release identity from manifest'));assert(!preg_match('/^\s*PACKAGE_VERSION:\s*1\.0\.0-rc\./m',$s));}
});
if($failures!==[])exit(1);fwrite(STDOUT,"All C2-L release-governance tests passed.\n");
