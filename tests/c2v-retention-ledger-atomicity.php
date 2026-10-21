<?php
declare(strict_types=1);
/** CF02-FR-031/034: source-complete purge/ledger atomicity simulations.
 * WPDB fake models transaction rollback; actual storage engines and external
 * provider deletion must be verified on staging before production acceptance.
 */
if(!defined('ARRAY_A'))define('ARRAY_A','ARRAY_A');
function wp_json_encode(mixed $value,int $flags=0):string|false{return json_encode($value,$flags);}
require_once dirname(__DIR__).'/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');
use Sabri\CF02\Infrastructure\WordPress\OperationsRepository;
use Sabri\CF02\Domain\SupportCaseId;
final class C2VRetentionDb {
    public string $prefix='wp_';
    public bool $caseExists=true;
    public array $retention=[];
    public array $events=[];
    public array $audits=[];
    public string $failure='none';
    public int $commitCount=0;
    public int $rollbackCount=0;
    public int $retentionWrites=0;
    private ?array $snapshot=null;
    public function prepare(string $sql,mixed ...$args):string{return $sql;}
    public function get_row(string $sql,string $type):?array{
        return $this->caseExists&&str_contains($sql,'FROM wp_cf02_cases')
            ? ['case_uuid'=>'CF02-CASE-X','category'=>'technical']:null;
    }
    public function get_var(string $sql):mixed{
        if(str_contains($sql,'COUNT(*)')&&str_contains($sql,'wp_cf02_holds'))
            return $this->failure==='hold' ? false : '0';
        return null;
    }
    public function get_results(string $sql,string $type):array{return [];}
    public function insert(string $table,array $data):int|false{
        if(str_contains($table,'cf02_retention')){
            $this->retentionWrites++;
            if(($this->failure==='tombstone'&&$this->retentionWrites===1)
                ||($this->failure==='final_ledger'&&$this->retentionWrites===2))return false;
            $this->retention[]=$data;return 1;
        }
        if(str_contains($table,'cf02_events')){
            if($this->failure==='event')return false;
            $this->events[]=$data;return 1;
        }
        if(str_contains($table,'cf02_audit')){
            if($this->failure==='audit')return false;
            $this->audits[]=$data;return 1;
        }
        return false;
    }
    public function delete(string $table,array $where):int|false {
        if($this->failure==='child_delete'&&str_contains($table,'cf02_messages'))return false;
        if(str_contains($table,'cf02_cases')){$this->caseExists=false;return 1;}
        return 0;
    }
    public function query(string $sql):int|false{
        if($sql==='START TRANSACTION'){
            $this->snapshot=[$this->caseExists,$this->retention,$this->events,$this->audits];
            return 0;
        }
        if($sql==='ROLLBACK'){
            if($this->snapshot!==null){
                [$this->caseExists,$this->retention,$this->events,$this->audits]=$this->snapshot;
                $this->snapshot=null;
            }
            $this->rollbackCount++;return 0;
        }
        if($sql==='COMMIT'){
            if($this->failure==='commit')return false;
            $this->snapshot=null;$this->commitCount++;return 0;
        }
        if($this->failure==='delete_sql'&&str_starts_with($sql,'DELETE'))return false;
        return 0;
    }
}
$fail=[];$cases=['none','hold','tombstone','child_delete','delete_sql','event','audit','final_ledger','commit'];
foreach($cases as $i=>$mode){
    $db=new C2VRetentionDb();$db->failure=$mode;$GLOBALS['wpdb']=$db;
    $repo=new OperationsRepository();
    try{
        $repo->purgeCase('CF02-CASE-X',['all_targets_reconciled'=>true,'authorized'=>true],
            new DateTimeImmutable('2026-10-21T08:00:00Z'));
        $threw=false;
    }catch(Throwable){$threw=true;}
    $expectedFailure=$mode!=='none';
    $ok=$threw===$expectedFailure;
    $ok=$ok&&($expectedFailure
        ? $db->caseExists&&$db->retention===[]&&$db->events===[]&&$db->audits===[]&&$db->commitCount===0
        : !$db->caseExists&&count($db->retention)===2&&count($db->events)===1&&count($db->audits)===1&&$db->commitCount===1);
    fwrite($ok?STDOUT:STDERR,sprintf("%s %02d/9 %s\n",$ok?'PASS':'FAIL',$i+1,$mode));
    if(!$ok)$fail[]=$mode;
}
$ops=(string)file_get_contents(dirname(__DIR__).'/src/Infrastructure/WordPress/OperationsRepository.php');
$worker=(string)file_get_contents(dirname(__DIR__).'/src/Infrastructure/WordPress/RuntimeWorker.php');
$start=strpos($ops,'public function purgeCase(');
$end=strpos($ops,'public function dueRetention(',$start);
$body=substr($ops,$start,$end-$start);
$ledger=strpos($body,"'cf02-retention-v1', 'purged'");
$event=strpos($body,'SupportRetentionPurgeCompleted');
$commit=strrpos($body,'});');
if($ledger===false||$event===false||$commit===false||!($event<$ledger&&$ledger<$commit)){
    $fail[]='Final ledger must be inside the purge transaction, after completion event';
}
if(str_contains($worker,"'cf02-retention-v1', 'purged'")||
    str_contains($worker,"'reason' => $error->getMessage()")){
    $fail[]='Worker must not produce duplicate post-commit purge ledger or leak raw exception messages';
}
printf("C2-V retention ledger atomicity: %d/9 scenarios passed; %d failures; staged MySQL is still required.\n",
    9-count(array_filter($fail,static fn($x)=>in_array($x,$cases=['none','hold','tombstone','child_delete','delete_sql','event','audit','final_ledger','commit'],true))),count($fail));
exit($fail===[]?0:1);
