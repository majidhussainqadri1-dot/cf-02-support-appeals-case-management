<?php

declare(strict_types=1);
/**
 * Forty source/document consistency review checks.
 * Not a substitute for staging, deployed code or live verification.
 */
require_once dirname(__DIR__) . '/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__) . '/src');

use Sabri\CF02\Contracts\RequiredCompanionContracts as Companion;
use Sabri\CF02\Contracts\SupportContractCatalog as Contracts;
use Sabri\CF02\Future\FeatureCatalog as Future;
use Sabri\CF02\Infrastructure\WordPress\SchemaCompletion as Schema;

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $value = file_get_contents($root . '/' . $path);
    if (!is_string($value)) { throw new RuntimeException('Missing source: ' . $path); }
    return $value;
};
$tick = chr(96);
$docs = [];
foreach (['COMPANION-CONTRACTS','ACTIVATION-GATES','PRODUCTION-READINESS',
          'FUTURE24-IMPLEMENTATION','RELEASE-PROCESS','REQUIREMENTS','TRACEABILITY'] as $name) {
    $docs[$name] = $read('docs/' . $name . '.md');
}
$defs = Companion::definitions();
$checkResults = [];
$check = static function (string $name, bool $ok) use (&$checkResults): void {
    $checkResults[] = ['name'=>$name, 'passed'=>$ok];
    fwrite($ok ? STDOUT : STDERR, sprintf("%s %02d/40 %s\n", $ok ? 'PASS' : 'FAIL', count($checkResults), $name));
};
foreach ($defs as $key=>$d) {
    // 1-17: Exact registered owner, capability and mandatory/conditional gate.
    $row = '| ' . $tick . $key . $tick . ' | ' . $d['owner'] . ' | ' . $tick . $d['capability'] . $tick . ' |';
    $gate = $d['required'] ? $row . ' Mandatory |' : $row . ' ' . $tick . 'enabled: true' . $tick;
    $check('Owner/capability manifest ' . $key, str_contains($docs['COMPANION-CONTRACTS'], $gate));
}
$required = array_filter($defs, static fn(array $d):bool=>$d['required']);
$conditional = array_filter($defs, static fn(array $d):bool=>!$d['required']);
$check('17 dependency records: 14 mandatory, 3 conditional',count($defs)===17&&count($required)===14&&count($conditional)===3); //18
$check('Activation documentation covers mandatory dependencies',
    array_reduce(array_keys($required),static fn(bool $ok,string $k):bool=>$ok&&str_contains($docs['ACTIVATION-GATES'],chr(96).$k.chr(96)),true)); //19
$check('Activation documentation covers optional dependencies',
    array_reduce(array_keys($conditional),static fn(bool $ok,string $k):bool=>$ok&&str_contains($docs['ACTIVATION-GATES'],chr(96).$k.chr(96)),true)); //20
$check('Provider health freshness is documented',
    str_contains($docs['COMPANION-CONTRACTS'],"'health_checked_at'")&&
    str_contains($docs['COMPANION-CONTRACTS'],'15 minutes')&&
    str_contains($docs['ACTIVATION-GATES'],'15 minutes')); //21
$version=Contracts::CONTRACT_VERSION;
$check('Production-readiness contract matches canonical',str_contains($docs['PRODUCTION-READINESS'],'Contract: '.$tick.$version.$tick)); //22
$check('Release-process contract matches canonical',str_contains($docs['RELEASE-PROCESS'],'- Contract: '.$tick.$version.$tick)); //23
$check('Future24 contract matches canonical',str_contains($docs['FUTURE24-IMPLEMENTATION'],'contract remains '.$tick.$version.$tick)); //24
$manifest=json_decode($read('release/manifest.json'),true,512,JSON_THROW_ON_ERROR);
$check('Release manifest contract matches canonical',($manifest['contract_version']??null)===$version); //25
$check('Release manifest schema matches executable',($manifest['database_schema_version']??null)===Schema::VERSION); //26
$check('README contract and schema match executable',
    str_contains($read('README.md'),$tick.$version.$tick.' / '.$tick.Schema::VERSION.$tick)); //27
$check('Plugin runtime version matches manifest',
    str_contains($read('cf-02-support-appeals-case-management.php'),'* Version: '.$manifest['plugin_version'])); //28
$check('Production-readiness does not claim external acceptance',
    str_contains($docs['PRODUCTION-READINESS'],'not-started external staging evidence')&&
    str_contains($docs['PRODUCTION-READINESS'],'No missing external evidence is converted into a pass.')); //29
$numbers=range(1,34);
$check('All 34 FR identifiers in requirements',
    array_reduce($numbers,static fn(bool $ok,int $n):bool=>$ok&&str_contains($docs['REQUIREMENTS'],sprintf('CF02-FR-%03d',$n)),true)); //30
$check('All 34 FR identifiers in evidence traceability',
    array_reduce($numbers,static fn(bool $ok,int $n):bool=>$ok&&str_contains($docs['TRACEABILITY'],'| '.sprintf('CF02-FR-%03d',$n).' |'),true)); //31
$futureIds=array_map(static fn(int $n):string=>sprintf('CF02-FUT-%03d',$n),range(1,24));
$check('All 24 Future24 IDs in executable registry',Future::count()===24&&array_keys(Future::all())===$futureIds); //32
$check('All 24 Future24 IDs in implementation plan',
    array_reduce($futureIds,static fn(bool $ok,string $id):bool=>$ok&&str_contains($docs['FUTURE24-IMPLEMENTATION'],'| '.$id.' |'),true)); //33
$check('All Future24 entries have explicit activation gate and guardrail',
    array_reduce(Future::all(),static fn(bool $ok,array $v):bool=>$ok&&$v['activation']==='feature-gated'&&trim($v['guardrail'])!=='',true)); //34
$check('Functional Future24 test file exists',is_file($root.'/tests/c2m-future24.php')); //35
$check('Two independent Future24 review test files exist',
    is_file($root.'/tests/c2m-review1.php')&&is_file($root.'/tests/c2m-review2.php')); //36
$check('File 00 identity, File 02 authentication and File 19 notification are distinct owners',
    ($defs['file_00_membership_contract']['owner']??'')==='File 00'&&
    ($defs['file_02_authentication_contract']['owner']??'')==='File 02'&&
    ($defs['file_19_notification_contract']['owner']??'')==='File 19'); //37
$check('File 17, File 18 and conditional clinical authority remain separate',
    ($defs['file_17_message_report_contract']['owner']??'')==='File 17'&&
    ($defs['file_18_marketplace_case_contract']['owner']??'')==='File 18'&&
    ($defs['clinical_safety_owner_contract']['required']??true)===false); //38
$check('Future PHP domain has no direct WordPress or network transport calls',
    (static function()use($root):bool{
        $files=glob($root.'/src/Future/*.php')?:[];
        if(count($files)<8)return false;
        foreach($files as $file){
            if(preg_match('/\b(?:add_action|add_filter|wp_insert_post|wp_update_post|wp_remote_get|wp_remote_post|curl_exec|file_put_contents)\s*\(/',(string)file_get_contents($file)))return false;
        }
        return true;
    })()); //39
$check('This 40-check regression suite is wired into composer and CI',
    str_contains($read('composer.json'),'tests/crossplan-40-review.php')&&
    str_contains($read('.github/workflows/ci.yml'),'tests/crossplan-40-review.php')); //40
if(count($checkResults)!==40){fwrite(STDERR,"Wrong checkpoint count\n");exit(2);}
$failed=array_filter($checkResults,static fn(array $v):bool=>!$v['passed']);
printf("Cross-plan checks: %d passed, %d failed; external evidence unverified.\n",40-count($failed),count($failed));
exit($failed===[]?0:1);
