<?php
declare(strict_types=1);
/** CF02-FR-015: independently test SLA alert/event database-atomicity.
 * Synthetic WPDB simulation; production MySQL/WordPress proof remains open.
 */
if (!defined('ARRAY_A')) define('ARRAY_A','ARRAY_A');
function wp_json_encode(mixed $value,int $options=0):string|false{return json_encode($value,$options);}
require_once dirname(__DIR__).'/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');
use Sabri\CF02\Infrastructure\WordPress\OperationsRepository;
use Sabri\CF02\Domain\SupportCaseId;
final class C2VSlaDb {
    public string $prefix='wp_';
    public array $sla;
    public array $events=[];
    public array $audits=[];
    public string $failStage='none';
    public int $commits=0;
    public int $rollbacks=0;
    private ?array $snap=null;
    public function __construct(string $caseId){
        $this->sla=['case_uuid'=>$caseId,'status'=>'running','record_version'=>1];
    }
    public function prepare(string $sql,mixed ...$args):string{return $sql;}
    public function get_row(string $sql,string $format):?array{
        if(str_contains($sql,'cf02_sla_timers'))return $this->sla;
        if(str_contains($sql,'cf02_events'))return null;
        return null;
    }
    public function get_var(string $sql):mixed{return null;}
    public function update(string $table,array $values,array $where):int|false{
        if(str_contains($table,'cf02_sla_timers')){
            if($this->failStage==='sla')return false;
            $this->sla=array_replace($this->sla,$values);
            return 1;
        }
        return false;
    }
    public function insert(string $table,array $values):int|false{
        if(str_contains($table,'cf02_events')){
            if($this->failStage==='event')return false;
            $this->events[]=$values;return 1;
        }
        if(str_contains($table,'cf02_audit')){
            if($this->failStage==='audit')return false;
            $this->audits[]=$values;return 1;
        }
        return false;
    }
    public function query(string $sql):int|false {
        if($sql==='START TRANSACTION'){
            if($this->failStage==='begin')return false;
            $this->snap=[$this->sla,$this->events,$this->audits];return 0;
        }
        if($sql==='COMMIT'){
            if($this->failStage==='commit')return false;
            $this->commits++;$this->snap=null;return 0;
        }
        if($sql==='ROLLBACK'){
            if($this->snap!==null){
                [$this->sla,$this->events,$this->audits]=$this->snap;
                $this->snap=null;
            }
            $this->rollbacks++;return 0;
        }
        return false;
    }
}
$plans=[];
foreach(['at_risk','breached'] as $status)foreach(['none','begin','sla','event','audit','commit'] as $stage)
    $plans[]=[$status,$stage];
$errors=[];
foreach($plans as $i=>[$status,$stage]){
    $caseId=SupportCaseId::generate()->value();
    $wpdb=new C2VSlaDb($caseId);$wpdb->failStage=$stage;$GLOBALS['wpdb']=$wpdb;
    $repo=new OperationsRepository();
    try{
        $version=$repo->markSlaStatusWithEvent($caseId,$status,
            ['status'=>$status,'priority'=>'P2','policy_id'=>'cf02-default','policy_version'=>'1.0.0'],
            new DateTimeImmutable('2026-10-21T12:00:00Z'));
        $rejected=false;
    }catch(Throwable){
        $version=0;$rejected=true;
    }
    $shouldReject=$stage!=='none';
    $good=$rejected===$shouldReject;
    if($shouldReject){
        $good=$good&&$wpdb->sla['status']==='running'&&$wpdb->sla['record_version']===1
            &&$wpdb->events===[]&&$wpdb->audits===[]&&$wpdb->commits===0;
        $good=$good&&$wpdb->rollbacks===($stage==='begin'?0:1);
    }else{
        $good=$good&&$wpdb->sla['status']===$status&&$wpdb->sla['record_version']===2
            &&$version===2&&count($wpdb->events)===1&&count($wpdb->audits)===1
            &&$wpdb->events[0]['event_type']===($status==='breached'?'SupportSlaBreached':'SupportSlaAtRisk')
            &&$wpdb->commits===1&&$wpdb->rollbacks===0;
    }
    $label=$status.' / '.$stage;
    fwrite($good?STDOUT:STDERR,sprintf("%s %02d/12 %s\n",$good?'PASS':'FAIL',$i+1,$label));
    if(!$good)$errors[]=$label;
}
$s=(string)file_get_contents(dirname(__DIR__).'/src/Infrastructure/WordPress/RuntimeWorker.php');
$r=(string)file_get_contents(dirname(__DIR__).'/src/Infrastructure/WordPress/OperationsRepository.php');
$start=strpos($s,'public function processSla(');
$end=strpos($s,'public function processKeyRotation(',$start);
$segment=substr($s,$start,$end-$start);
if(!str_contains($segment,'->markSlaStatusWithEvent(')||
    str_contains($segment,'->appendWorkerEvent(')||
    !str_contains($r,'public function markSlaStatusWithEvent(')){
    $errors[]='The worker must call the repository-owned atomic SLA transition';
}
printf("C2-V SLA event atomic simulation: %d/12 scenarios passed; %d total failures.\n",
    12-count(array_filter($errors,static fn(string $e):bool=>str_contains($e,' / '))),count($errors));
exit($errors===[]?0:1);
