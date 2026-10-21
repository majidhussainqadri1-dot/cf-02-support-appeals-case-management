<?php
declare(strict_types=1);
/**
 * C2-U: Forty NEW operational safety/control source checks across CF-02
 * and the central/companion plans. These are structural invariant checks.
 * They are NOT forty full-system end-to-end tests or proof of live operation.
 */
require_once dirname(__DIR__).'/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');
use Sabri\CF02\Contracts\RequiredCompanionContracts as Registry;

$src=static function(string $file):string{
    static $cache=[];
    if(!array_key_exists($file,$cache)){
        $path=dirname(__DIR__).'/'.$file;
        if(!is_file($path))throw new RuntimeException('Missing exact witness source: '.$file);
        $cache[$file]=(string)file_get_contents($path);
    }
    return $cache[$file];
};
$includes=static fn(string $file,string $fragment):bool=>str_contains($src($file),$fragment);
$excludes=static fn(string $file,string $fragment):bool=>!str_contains($src($file),$fragment);
$before=static function(string $file,string $first,string $second)use($src):bool{
    $s=$src($file);$a=strpos($s,$first);$b=strpos($s,$second);
    return $a!==false && $b!==false && $a<$b;
};
$registry=Registry::definitions();
$A='src/Activation/WordPressActivationEvidence.php';
$R='src/Contracts/RequiredCompanionContracts.php';
$G='src/Infrastructure/WordPress/GuestIntakeController.php';
$P='src/Infrastructure/WordPress/ProviderWebhookController.php';
$W='src/Infrastructure/WordPress/RuntimeWorker.php';
$O='src/Infrastructure/WordPress/OperationsRepository.php';
$N='src/Integration/NativeOwnerOutcomePolicy.php';
$V='src/Integration/NativeResultVersion.php';
$L='src/Infrastructure/WordPress/CompleteRestOverlay.php';
$D='src/Infrastructure/WordPress/RepairService.php';
$checks=[
    ['01 | Exact companion registry: 17 distinct owners/providers',fn()=>count($registry)===17],
    ['02 | Exactly 14 mandatory activation contracts',fn()=>count(array_filter($registry,static fn($x)=>$x['required']))===14],
    ['03 | Exactly 3 conditional-only owner contracts',fn()=>count(array_filter($registry,static fn($x)=>!$x['required']))===3],
    ['04 | File 18 remains marketplace decision owner',fn()=>($registry['file_18_marketplace_case_contract']['owner']??'')==='File 18'],
    ['05 | File 17 remains communication/report owner',fn()=>($registry['file_17_message_report_contract']['owner']??'')==='File 17'],
    ['06 | File 19 remains native notification owner',fn()=>($registry['file_19_notification_contract']['owner']??'')==='File 19'],
    ['07 | File 00 function presence never generates readiness',fn()=>$excludes($A,"function_exists('smc_membership_assertions')")],
    ['08 | Native owner default manifest is unready',fn()=>$includes($A,"'ready' => false")],
    ['09 | Owner health unknown is not healthy',fn()=>$includes($A,"'health' => 'unknown'")],
    ['10 | Exact deployed SHA is not fabricated by default',fn()=>$includes($A,"'source_sha' => ''")],
    ['11 | Owner contract requires fresh health timestamp',fn()=>$includes($R,"-15 minutes")],
    ['12 | Future-dated provider health has hard ceiling',fn()=>$includes($R,"+60 seconds")],
    ['13 | Source owner supplies explicit manifest by hook',fn()=>$includes($A,"cf02_dependency_contract_evidence")],
    ['14 | Emergency diversion precedes intake throttling',fn()=>$before($G,'EmergencyRunbookRegistry::classifyText','if (!$this->consumeRateLimit())')],
    ['15 | Case creation after guest continuation requires capability',fn()=>$includes($G,"hasCapability('case.create')")],
    ['16 | Guest pre-intake declares case not created',fn()=>$includes($G,"'case_created' => false")],
    ['17 | Guest throttling acquires MySQL named lock',fn()=>$includes($G,"SELECT GET_LOCK")],
    ['18 | Absent named lock never yields allowance',fn()=>$includes($G,"if ((string) $acquired !== '1')")],
    ['19 | Named lock released in finally path',fn()=>$includes($G,"SELECT RELEASE_LOCK") && $includes($G,"finally")],
    ['20 | Guest throttling requires durable transient set',fn()=>$includes($G,"set_transient($key, $count + 1, 900) === true")],
    ['21 | Signed provider request bounded to 300 seconds',fn()=>$includes($P,'> 300')],
    ['22 | Signed provider request body limited to 65536 bytes',fn()=>$includes($P,'strlen($body) > 65536')],
    ['23 | Incoming HMAC signs timestamp + raw body',fn()=>$includes($P,"hash_hmac('sha256', $timestamp . '.' . $body, $key)")],
    ['24 | Inbound replay compares exact payload fingerprint',fn()=>$includes($P,"hash_equals((string) $existing['payload_hash'], $hash)")],
    ['25 | Sender and provider metadata validated before use',fn()=>$includes($P,'InboundProviderMetadata::fromPayload($payload)')],
    ['26 | Inbound body forbids known prohibited secrets',fn()=>$includes($P,'SensitiveContentDetector::containsProhibitedSecret($body)')],
    ['27 | Native webhook uses single validated result policy',fn()=>$includes($P,'NativeOwnerOutcomePolicy::validate(')],
    ['28 | Native background worker uses same result policy',fn()=>$includes($W,'NativeOwnerOutcomePolicy::validate(')],
    ['29 | Native version cannot clamp on integer overflow',fn()=>$includes($V,'Native result version exceeds the supported integer range.')],
    ['30 | Successful native result requires owner proof',fn()=>$includes($N,'Successful native owner result requires an outcome reference.')],
    ['31 | Event published mark checks exact durable update',fn()=>$includes($O,'Published event state was not durably recorded.')],
    ['32 | Event retry mark checks exact durable update',fn()=>$includes($O,'Event retry state was not durably recorded.')],
    ['33 | Published worker event is announced only after status persistence',fn()=>$before($W,'$this->repository->markEventPublished(','do_action(\'cf02_domain_event_published\'')],
    ['34 | SQL list outage throws non-input LogicException',fn()=>$includes($O,"throw new LogicException('Support database list query failed.')")],
    ['35 | Private authorized-query outage has HTTP 503 path',fn()=>$includes($L,"'cf02_query_unavailable'") && $includes($L,"['status'=>503")],
    ['36 | Invalid API query has separate HTTP 400 path',fn()=>$includes($L,"'cf02_invalid_query'") && $includes($L,"['status'=>400")],
    ['37 | Repair requires scoped approved and recently authenticated actor',fn()=>$includes($D,"hasCapability('repair.execute')") && $includes($D,'recentlyAuthenticated($at)')],
    ['38 | Unknown legal hold state refuses destructive purge',fn()=>$includes($O,"$activeHolds !== 0 && $activeHolds !== '0'")],
    ['39 | Retention child deletions use checked DML helpers',fn()=>$includes($O,'private function purgeDelete(') && $includes($O,'private function purgeQuery(')],
    ['40 | Purge completion evidence is within the deletion transaction',fn()=>($body=substr($src($O),strpos($src($O),'public function purgeCase('),strpos($src($O),'public function dueRetention(')-strpos($src($O),'public function purgeCase('))) && false],
];
// Replace the final placeholder with an explicit local scoped verification.
$checks[39][1]=static function()use($src,$O):bool{
    $source=$src($O);
    $a=strpos($source,'public function purgeCase(');
    $b=strpos($source,'public function dueRetention(',$a);
    $body=substr($source,$a,$b-$a);
    $start=strpos($body,'$this->transaction(function');
    $event=strpos($body,'SupportRetentionPurgeCompleted');
    $close=strrpos($body,'});');
    return $start!==false && $event!==false && $close!==false && $start<$event && $event<$close;
};
if(count($checks)!==40){fwrite(STDERR,"Expected forty safeguards.\n");exit(2);}
$failed=[];
foreach($checks as $i=>[$label,$assert]){
    try{$ok=$assert()===true;}catch(Throwable){$ok=false;}
    fwrite($ok?STDOUT:STDERR,sprintf("%s %02d/40 %s\n",$ok?'PASS':'FAIL',$i+1,$label));
    if(!$ok)$failed[]=$label;
}
printf("CF-02 C2-U forty operational source safety controls: %d/40 passed, %d failed. Staging/live separate.\n",40-count($failed),count($failed));
exit($failed===[]?0:1);
