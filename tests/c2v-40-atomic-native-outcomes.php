<?php
declare(strict_types=1);

/**
 * C2-V: 40 database-transaction and native-owner/SLA reconciliation scenarios
 * cross-checking CF02-FR-013/014/022 and CEN-REL/CF-01/17/18/19 boundaries.
 * A deterministic WPDB simulation tests code ordering and rollback handling,
 * NOT real MySQL isolation or provider interoperability.
 */
if(!defined('ARRAY_A')) define('ARRAY_A','ARRAY_A');
function wp_json_encode(mixed $value,int $flags=0):string|false{return json_encode($value,$flags);}
require_once dirname(__DIR__).'/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');

use Sabri\CF02\Domain\SupportCaseId;
use Sabri\CF02\Infrastructure\WordPress\OperationsRepository;

final class C2VFakeWpdb {
    public string $prefix='wp_';
    public array $command;
    public array $sla;
    public array $events=[];
    public array $audits=[];
    public bool $failSla=false;
    public bool $failCommand=false;
    public bool $failEvent=false;
    public bool $failAudit=false;
    public bool $failCommit=false;
    public int $rollbacks=0;
    public int $commits=0;
    private ?array $snapshot=null;
    public function __construct(string $caseId,string $pauseMode){
        $this->command=['command_uuid'=>'CF02-CMD-T-01','case_uuid'=>$caseId,'state'=>'pending',
            'record_version'=>1,'outcome_ref'=>null,'attempts'=>0];
        $this->sla=['case_uuid'=>$caseId,'record_version'=>1,'status'=>$pauseMode==='running'?'running':'paused',
            'pause_reason'=>$pauseMode==='waiting'?'waiting_provider':($pauseMode==='other'?'privacy_review':null),
            'paused_at'=>'2026-10-21 00:00:00.000000',
            'first_response_deadline'=>'2026-10-21 12:00:00.000000',
            'update_deadline'=>'2026-10-21 12:00:00.000000',
            'resolution_deadline'=>'2026-10-22 12:00:00.000000'];
    }
    public function prepare(string $sql,mixed ...$args):string{return $sql;}
    public function get_row(string $sql,string $format):?array {
        if(str_contains($sql,'wp_cf02_commands'))return $this->command;
        if(str_contains($sql,'wp_cf02_sla_timers'))return $this->sla;
        if(str_contains($sql,'wp_cf02_events'))return null;
        return null;
    }
    public function get_var(string $sql):mixed {return null;}
    public function update(string $table,array $set,array $where):int|false{
        if(str_contains($table,'cf02_commands')){
            if($this->failCommand)return false;
            $this->command=array_replace($this->command,$set);return 1;
        }
        if(str_contains($table,'cf02_sla_timers')){
            if($this->failSla)return false;
            $this->sla=array_replace($this->sla,$set);return 1;
        }
        return false;
    }
    public function insert(string $table,array $row):int|false {
        if(str_contains($table,'cf02_events')){
            if($this->failEvent)return false;
            $this->events[]=$row;return 1;
        }
        if(str_contains($table,'cf02_audit')){
            if($this->failAudit)return false;
            $this->audits[]=$row;return 1;
        }
        return false;
    }
    public function query(string $sql):int|false{
        if($sql==='START TRANSACTION'){
            $this->snapshot=[$this->command,$this->sla,$this->events,$this->audits];
            return 0;
        }
        if($sql==='COMMIT'){
            if($this->failCommit)return false;
            $this->snapshot=null;
            $this->commits++;return 0;
        }
        if($sql==='ROLLBACK'){
            if($this->snapshot!==null){
                [$this->command,$this->sla,$this->events,$this->audits]=$this->snapshot;
                $this->snapshot=null;
            }
            $this->rollbacks++;return 0;
        }
        return false;
    }
}
$states=['succeeded','failed','outcome_uncertain','retry'];
$cases=[];
foreach($states as $state){
    foreach(['waiting','other','running'] as $pause){
        $cases[]=['state'=>$state,'pause'=>$pause,'failure'=>'none'];
    }
}
foreach($states as $state){
    foreach(['waiting','other','running'] as $pause){
        $cases[]=['state'=>$state,'pause'=>$pause,'failure'=>'sla'];
    }
}
foreach($states as $state){
    foreach(['command','event','audit','commit'] as $failure){
        $cases[]=['state'=>$state,'pause'=>'waiting','failure'=>$failure];
    }
}
if(count($cases)!==40)throw new RuntimeException('Must run forty distinct scenarios.');
$failures=[];
foreach($cases as $i=>$case){
    $caseId=SupportCaseId::generate()->value();
    $wpdb=new C2VFakeWpdb($caseId,$case['pause']);
    $GLOBALS['wpdb']=$wpdb;
    $flag=$case['failure'];
    if($flag==='command')$wpdb->failCommand=true;
    if($flag==='event')$wpdb->failEvent=true;
    if($flag==='audit')$wpdb->failAudit=true;
    if($flag==='sla')$wpdb->failSla=true;
    if($flag==='commit')$wpdb->failCommit=true;
    $repo=new OperationsRepository();
    $state=$case['state'];
    $isTerminal=in_array($state,['succeeded','failed'],true);
    $shouldFail=$flag==='command'||$flag==='event'||$flag==='audit'||$flag==='commit'
        ||($flag==='sla'&&$isTerminal&&$case['pause']==='waiting');
    $at=new DateTimeImmutable('2026-10-21T08:00:00Z');
    try{
        $repo->recordCommandResultWithEvidence('CF02-CMD-T-01',$state,
            $state==='succeeded'?'owner-evidence-1':null,1,null,
            ['command_ref'=>'CF02-CMD-T-01','state'=>$state,'case_ref'=>$caseId],
            'test-idempotency-123456789','native_result_reconciliation',$at);
        $thrown=false;
    }catch(Throwable $e){
        $thrown=true;
    }
    $slaShouldResume=$isTerminal && $case['pause']==='waiting';
    $pass=($thrown===$shouldFail);
    if($shouldFail){
        $pass=$pass&&$wpdb->command['state']==='pending'&&$wpdb->sla['status']!==($slaShouldResume?'running':'never')
            &&$wpdb->events===[]&&$wpdb->audits===[]&&$wpdb->rollbacks===1&&$wpdb->commits===0;
    }else{
        $pass=$pass&&$wpdb->command['state']===$state
            &&count($wpdb->events)===1&&count($wpdb->audits)===1
            &&$wpdb->commits===1&&$wpdb->rollbacks===0
            &&$wpdb->sla['status']===($slaShouldResume?'running':($case['pause']==='running'?'running':'paused'));
    }
    $label=sprintf('state=%s pause=%s failure=%s',$state,$case['pause'],$flag);
    fwrite($pass?STDOUT:STDERR,sprintf('%s %02d/40 %s%s',$pass?'PASS':'FAIL',$i+1,$label,"\n"));
    if(!$pass)$failures[]=$label;
}
$source=(string)file_get_contents(dirname(__DIR__).'/src/Infrastructure/WordPress/OperationsRepository.php');
$worker=(string)file_get_contents(dirname(__DIR__).'/src/Infrastructure/WordPress/RuntimeWorker.php');
$rest=(string)file_get_contents(dirname(__DIR__).'/src/Infrastructure/WordPress/ProviderWebhookController.php');
$a=strpos($source,'public function recordCommandResultWithEvidence(');
$b=strpos($source,'public function inboundReceipt(',$a);
$chunk=substr($source,$a,$b-$a);
$wired=strpos($chunk,'SupportNativeCommandResultRecorded')<strpos($chunk,'$this->resumeSla(')
    && strpos($chunk,'$this->resumeSla(')<strrpos($chunk,'});');
if(!$wired || str_contains($worker,"$"."this->repository->resumeSla(")){
    $failures[]='worker must not independently retry SLA after terminal persistence';
}
if(!str_contains($rest,'Historical committed outcomes may predate atomic SLA reconciliation')){
    $failures[]='signed callback must retry historical terminal SLA on verified replay';
}
printf("C2-V native outcome/SLA atomic simulation: %d/40 cases pass; %d failures; real DB/staging still required.\n",
    40-count(array_filter($failures,static fn(string $x):bool=>str_starts_with($x,'state='))),
    count($failures));
exit($failures===[]?0:1);
