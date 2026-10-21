<?php
declare(strict_types=1);
/** C2-Y: 40 cross-plan tests of native linked-object privacy class and event
 * atomicity. Fake WPDB models transactional rollback, NOT live engine proof.
 */
if(!defined('ARRAY_A'))define('ARRAY_A','ARRAY_A');
function wp_json_encode(mixed $value,int $options=0):string|false{
    return json_encode($value,$options);
}
require_once dirname(__DIR__).'/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');

use Sabri\CF02\Authorization\PrincipalContext;
use Sabri\CF02\Domain\SupportCaseId;
use Sabri\CF02\Infrastructure\WordPress\OperationsRepository;

final class C2YLinkedDb {
    public string $prefix='wp_';
    public array $linked=[];
    public array $events=[];
    public array $audit=[];
    public int $commits=0;
    public int $rollbacks=0;
    public int $updates=0;
    public int $inserts=0;
    public string $failure='none';
    public string $caseId='';
    private ?array $snapshot=null;

    public function prepare(string $sql,mixed ...$args):string{return $sql;}
    public function get_row(string $sql,string $format):?array{
        if(str_contains($sql,'FROM wp_cf02_cases')){
            return ['case_uuid'=>$this->caseId,'requester_ref'=>'user:abc','queue_key'=>'technical'];
        }
        if(str_contains($sql,'FROM wp_cf02_case_links')){
            return $this->linked===[]?null:$this->linked;
        }
        if(str_contains($sql,'FROM wp_cf02_events')) {
            return null;
        }
        return null;
    }
    public function get_var(string $sql):mixed{
        if(str_contains($sql,'SELECT record_version')) return '1';
        return null;
    }
    public function update(string $table,array $set,array $where):int|false{
        if(!str_contains($table,'cf02_case_links')) return false;
        $this->updates++;
        if($this->failure==='write')return false;
        $this->linked=array_replace($this->linked,$set);
        return 1;
    }
    public function insert(string $table,array $row):int|false{
        $this->inserts++;
        if(str_contains($table,'cf02_case_links')){
            if($this->failure==='write')return false;
            $this->linked=$row;return 1;
        }
        if(str_contains($table,'cf02_events')){
            if($this->failure==='event')return false;
            $this->events[]=$row;return 1;
        }
        if(str_contains($table,'cf02_audit')){
            if($this->failure==='audit')return false;
            $this->audit[]=$row;return 1;
        }
        return false;
    }
    public function query(string $sql):int|false{
        if($sql==='START TRANSACTION'){
            if($this->failure==='begin')return false;
            $this->snapshot=[$this->linked,$this->events,$this->audit];
            return 0;
        }
        if($sql==='COMMIT'){
            $this->snapshot=null;$this->commits++;return 0;
        }
        if($sql==='ROLLBACK'){
            if($this->snapshot!==null){
                [$this->linked,$this->events,$this->audit]=$this->snapshot;
                $this->snapshot=null;
            }
            $this->rollbacks++;return 0;
        }
        return false;
    }
}
$at=new DateTimeImmutable('2026-10-21T12:00:00Z');
$context=new PrincipalContext('user:abc',2,['requester'],[],[],false,$at,
    'File 00','1.0.0',$at,$at->modify('+10 minutes'));
$classes=['C1','C2','C3','C4'];
$failures=[];
$cases=0;
foreach(['new','existing'] as $mode)foreach($classes as $class)
foreach(['none','begin','write','event','audit'] as $stage){
    $caseId=SupportCaseId::generate();
    $db=new C2YLinkedDb();$db->caseId=$caseId->value();
    $projection=['approved'=>true,'safe_reason'=>'owner_context'];
    $hash=hash('sha256',json_encode($projection,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    if($mode==='existing'){
        $db->linked=[
            'link_uuid'=>'CF02-LNK-ORIGINAL',
            'case_uuid'=>$caseId->value(),'owner_key'=>'messages_reports',
            'object_type'=>'message','object_ref'=>'msg-125','object_version'=>'1',
            'privacy_class'=>'C1','projection_hash'=>$hash,'state'=>'active',
        ];
    }
    $initial=$db->linked;
    $db->failure=$stage;$GLOBALS['wpdb']=$db;
    $repo=new OperationsRepository();
    $shouldFail=$stage!=='none' && !(
        $mode==='existing' && $stage==='write' && $class==='C1'
    );
    try{
        $id=$repo->linkObject($caseId,$context,'messages_reports','message',
            'msg-125','1',$class,$projection,'key-ABC-12345678900',$at);
        $thrown=false;
    }catch(Throwable){$thrown=true;$id=null;}
    $ok=$thrown===$shouldFail;
    if($shouldFail){
        $ok=$ok&&$db->linked===$initial&&$db->events===[]&&$db->audit===[]
            &&$db->commits===0&&$db->rollbacks===($stage==='begin'?0:1);
    }else{
        $ok=$ok&&is_string($id)&&$db->linked['privacy_class']===$class
            &&count($db->events)===1&&count($db->audit)===1
            &&$db->commits===1&&$db->rollbacks===0;
        if($ok){
            $eventPayload=json_decode((string)$db->events[0]['payload_json'],true);
            $ok=is_array($eventPayload)
                &&($eventPayload['privacy_class']??null)===$class
                &&($eventPayload['object_version']??null)==='1';
        }
    }
    $cases++;
    $label="$mode:$class:$stage";
    fwrite($ok?STDOUT:STDERR,sprintf("%s %02d/40 %s\n",$ok?'PASS':'FAIL',$cases,$label));
    if(!$ok)$failures[]=$label;
}
$path=dirname(__DIR__).'/src/Infrastructure/WordPress/OperationsRepository.php';
$source=(string)file_get_contents($path);
$a=strpos($source,'public function linkObject(');
$b=strpos($source,'public function ensureSlaTimer(',$a);
$body=substr($source,$a,$b-$a);
if(!str_contains($body,'SELECT * FROM {$this->tables[\'case_links\']} WHERE link_uuid=%s LIMIT 1 FOR UPDATE')
    || !str_contains($body,"hash_equals((string) $"."existing['privacy_class'], $"."privacyClass)")
    || !str_contains($body,"'privacy_class' => $"."privacyClass")
    || !str_contains($body,'$this->transaction(')){
    $failures[]='link-lock/privacy-transition/atomicity-wiring';
}
printf("CF-02 C2-Y linked native privacy transaction: %d/%d passed, %d failed; staged concurrency unverified.\n",
    $cases-count(array_filter($failures,static fn($x)=>str_contains($x,':'))),$cases,count($failures));
exit($cases===40 && $failures===[]?0:1);
