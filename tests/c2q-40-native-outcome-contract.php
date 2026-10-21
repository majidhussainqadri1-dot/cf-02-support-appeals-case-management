<?php
declare(strict_types=1);

/**
 * C2-Q: 40 new concrete native-owner result security vectors.
 * Requirement CF02-FR-022; scope is native outcome validation on *both*
 * command-worker and signed webhook interfaces. It is not 40 full end-to-end
 * integration tests or proof of an authenticated external provider.
 */
require_once dirname(__DIR__).'/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');

use Sabri\CF02\Integration\NativeOwnerOutcomePolicy as Policy;
$max = (string)PHP_INT_MAX;
$valid = [
 ['Successful minimum version', ['state'=>'succeeded','native_version'=>1,'outcome_ref'=>'owner-proof-1'],1],
 ['Successful higher version', ['state'=>'succeeded','native_version'=>10,'outcome_ref'=>'owner-proof-2'],2],
 ['Canonical decimal version', ['state'=>'succeeded','native_version'=>'10','outcome_ref'=>'owner-proof-3'],2],
 ['Successful maximum version', ['state'=>'succeeded','native_version'=>$max,'outcome_ref'=>'owner-proof-4'],2],
 ['Failed with evidence ref', ['state'=>'failed','native_version'=>2,'outcome_ref'=>'denial-123'],2],
 ['Failed with no reference', ['state'=>'failed','native_version'=>2],2],
 ['Outcome uncertain without reference', ['state'=>'outcome_uncertain','native_version'=>2],2],
 ['Outcome uncertain with owner reference', ['state'=>'outcome_uncertain','native_version'=>2,'outcome_ref'=>'wait-1'],2],
 ['Maximum schema-size proof ref', ['state'=>'succeeded','native_version'=>2,'outcome_ref'=>str_repeat('x',191)],2],
 ['UTF-8 reference preserves exact Unicode', ['state'=>'succeeded','native_version'=>2,'outcome_ref'=>'تصدیق-١'],2],
];
$baseline=['state'=>'succeeded','native_version'=>3,'outcome_ref'=>'owner-proof-3'];
$invalid = [
 ['State missing', ['native_version'=>3,'outcome_ref'=>'e'],2],
 ['Null state', array_replace($baseline,['state'=>null]),2],
 ['Numeric state', array_replace($baseline,['state'=>1]),2],
 ['Uppercase state', array_replace($baseline,['state'=>'SUCCEEDED']),2],
 ['Empty state', array_replace($baseline,['state'=>'']),2],
 ['Retry is not an owner-result state', array_replace($baseline,['state'=>'retry']),2],
 ['Dead-letter is not an owner-result state', array_replace($baseline,['state'=>'dead_letter']),2],
 ['Boolean state', array_replace($baseline,['state'=>true]),2],
 ['Array state', array_replace($baseline,['state'=>['succeeded']]),2],
 ['Succeeded with missing proof', ['state'=>'succeeded','native_version'=>3],2],
 ['Succeeded with empty proof', array_replace($baseline,['outcome_ref'=>'']),2],
 ['Padded proof rejected', array_replace($baseline,['outcome_ref'=>' owner-proof-3 ']),2],
 ['Newline proof rejected', array_replace($baseline,['outcome_ref'=>"owner\nproof"]),2],
 ['NUL proof rejected', array_replace($baseline,['outcome_ref'=>"owner\0proof"]),2],
 ['Malformed UTF-8 proof', array_replace($baseline,['outcome_ref'=>"\xC3\x28"]),2],
 ['Overlong database proof', array_replace($baseline,['outcome_ref'=>str_repeat('a',192)]),2],
 ['Array proof', array_replace($baseline,['outcome_ref'=>['owner-proof']]),2],
 ['Numeric proof', array_replace($baseline,['outcome_ref'=>123]),2],
 ['Null succeeded proof', array_replace($baseline,['outcome_ref'=>null]),2],
 ['Expected version zero', $baseline,0],
 ['Expected version negative', $baseline,-3],
 ['Native version missing', ['state'=>'succeeded','outcome_ref'=>'e'],2],
 ['Native version null', array_replace($baseline,['native_version'=>null]),2],
 ['Native version zero', array_replace($baseline,['native_version'=>0]),2],
 ['Native version zero string', array_replace($baseline,['native_version'=>'0']),2],
 ['Native version negative', array_replace($baseline,['native_version'=>-1]),2],
 ['Floating native version', array_replace($baseline,['native_version'=>1.0]),2],
 ['Scientific notation native version', array_replace($baseline,['native_version'=>'1e3']),2],
 ['Oversized native version', array_replace($baseline,['native_version'=>str_repeat('9',38)]),2],
 ['Stale native version', array_replace($baseline,['native_version'=>3]),4],
];
if (count($valid)!==10||count($invalid)!==30) {
    throw new RuntimeException('The C2-Q matrix must have exactly 40 independent cases.');
}
$worker=(string)file_get_contents(dirname(__DIR__).'/src/Infrastructure/WordPress/RuntimeWorker.php');
$callback=(string)file_get_contents(dirname(__DIR__).'/src/Infrastructure/WordPress/ProviderWebhookController.php');
foreach (['NativeOwnerOutcomePolicy::validate(', '$validated[\'native_version\']', '$validated[\'outcome_ref\']'] as $witness) {
    if (!str_contains($worker,$witness) || !str_contains($callback,$witness)) {
        throw new RuntimeException('Shared native-owner validation is not wired into both result paths.');
    }
}
$count=0;$failed=[];
$test = static function(string $label, array $result, int $expected, bool $shouldPass) use (&$count,&$failed):void {
    ++$count;
    try {
        $parsed=Policy::validate($result,$expected);
        $pass=$shouldPass && $parsed['state']===$result['state']
            && $parsed['native_version']>=$expected
            && is_string($parsed['outcome_ref']);
    } catch (Throwable) {
        $pass=!$shouldPass;
    }
    fwrite($pass?STDOUT:STDERR,sprintf("%s %02d/40 %s\n",$pass?'PASS':'FAIL',$count,$label));
    if (!$pass) $failed[]=$label;
};
foreach($valid as [$label,$result,$min]) $test($label,$result,$min,true);
foreach($invalid as [$label,$result,$min]) $test($label,$result,$min,false);
printf("CF-02 40 native outcome vectors: %d/40 passed, %d failed; provider integration remains unverified.\n",40-count($failed),count($failed));
exit($failed===[]?0:1);
