<?php

declare(strict_types=1);

/** Forty bounded replay tests for case/category legal-hold review dates.
 * CF02-FR-031 / CEN-PRIV: distinct review deadlines are not the same mutation.
 * No live database, real owner assertion or actual legal review is assumed.
 */
if (!defined('ARRAY_A')) { define('ARRAY_A', 'ARRAY_A'); }
require_once dirname(__DIR__) . '/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');

use Sabri\CF02\Infrastructure\WordPress\OperationsRepository;
use Sabri\CF02\Authorization\PrincipalContext;
use Sabri\CF02\Domain\SupportCaseId;

final class C2WHoldDb {
    public string $prefix='wp_';
    public array $hold=[];
    public string $caseId='';
    public int $writes=0;
    public function prepare(string $sql, mixed ...$args):string { return $sql; }
    public function get_row(string $sql, string $format):?array {
        if (str_contains($sql, 'FROM wp_cf02_cases')) {
            return ['case_uuid'=>$this->caseId,'requester_ref'=>'user:abc',
                    'queue_key'=>'technical','category'=>'technical'];
        }
        if (str_contains($sql, 'FROM wp_cf02_holds')) { return $this->hold; }
        return null;
    }
    public function query(string $sql):int { ++$this->writes;return 0; }
    public function insert(string $table,array $data):int { ++$this->writes;return 1; }
}

$now=new DateTimeImmutable('2026-10-21T08:00:00Z');
$actor=new PrincipalContext(
    'user:abc',2,['requester'],[],[],false,$now,
    'File 00','1.0.0',$now,$now->modify('+10 minutes')
);
$baseDue=$now->modify('+30 days');
$offsets=[0,1,-1,2,-2,3,-3,4,-4,5,-5,6,-6,7,-7,8,-8,9,-9,10];
if(count($offsets)!==20)throw new RuntimeException('Invalid test corpus');
$fail=[];$number=0;
foreach(['case','category'] as $type){
    foreach($offsets as $offset){
        ++$number;
        $id=SupportCaseId::generate();
        $db=new C2WHoldDb();
        $db->caseId=$id->value();
        $db->hold=[
            'hold_uuid'=>'CF02-HOLD-EXISTING',
            'case_uuid'=>$type==='case'?$id->value():null,
            'category'=>$type==='category'?'technical':null,
            'reason_code'=>'legal_review',
            'authority_ref'=>'AUTH-01',
            'review_due_at'=>$baseDue->format('Y-m-d H:i:s.u'),
        ];
        $GLOBALS['wpdb']=$db;
        $repository=new OperationsRepository();
        $requestDue=$offset===0?$baseDue:$baseDue->modify(($offset>0?'+':'').$offset.' days');
        $returned=false;$collision=false;
        try{
            $value=$type==='case'
                ?$repository->applyHold($id,$actor,'legal_review','AUTH-01',$requestDue,'same-hold-key',$now)
                :$repository->applyCategoryHold('technical',$actor,'legal_review','AUTH-01',$requestDue,'same-hold-key',$now);
            $returned=($value['hold_uuid']??'')==='CF02-HOLD-EXISTING';
        }catch(RuntimeException $e){
            $collision=str_contains($e->getMessage(),'Hold idempotency collision')
                || str_contains($e->getMessage(),'Category hold idempotency collision');
        }
        $good=($offset===0?$returned&&!$collision:$collision&&!$returned)&&$db->writes===0;
        if(!$good)$fail[]="$type offset=$offset";
        fwrite($good?STDOUT:STDERR,sprintf("%s %02d/40 %s hold due offset %+d days\n",
            $good?'PASS':'FAIL',$number,$type,$offset));
    }
}
printf("C2-W case/category hold review date replay: %d/40 passed; %d failed; live legal retention not verified.\n",
    40-count($fail),count($fail));
exit($fail===[]?0:1);
