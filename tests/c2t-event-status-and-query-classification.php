<?php
declare(strict_types=1);
/** C2-T: verify failures in event delivery persistence never masquerade as successful dispatch,
 * and protected-list SQL failures select server-unavailable, not user-invalid handling.
 * Synthetic PHP/fake WP adapter evidence only.
 */
if (!defined('ARRAY_A')) define('ARRAY_A','ARRAY_A');
$GLOBALS['cf02_test_event_actions']=[];
function apply_filters(string $name,mixed $default,mixed ...$args):mixed {
    return $name==='cf02_publish_domain_event' ? ['accepted'=>true] : $default;
}
function do_action(string $name,mixed ...$args):void {
    $GLOBALS['cf02_test_event_actions'][]=$name;
}
require_once dirname(__DIR__).'/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');
use Sabri\CF02\Infrastructure\WordPress\OperationsRepository;
use Sabri\CF02\Infrastructure\WordPress\RuntimeWorker;
use Sabri\CF02\Security\DataCipher;

final class C2TDb {
    public string $prefix='wp_';
    public array $updates=[];
    public array $results=[1];
    public mixed $rows=[];
    public function prepare(string $query,mixed ...$args):string{return $query;}
    public function get_results(string $query,string $type):mixed {return $this->rows;}
    public function update(string $table,array $fields,array $where):int|false {
        $this->updates[]=['table'=>$table,'fields'=>$fields,'where'=>$where];
        return array_shift($this->results) ?? false;
    }
}
$checks=0;$failed=[];
$test=static function(string $label,bool $ok)use(&$checks,&$failed):void{
    ++$checks;
    if(!$ok)$failed[]=$label;
    fwrite($ok?STDOUT:STDERR,sprintf("%s %02d %s\n",$ok?'PASS':'FAIL',$checks,$label));
};
$reject=static function(callable $fn):bool{
    try{$fn();return false;}catch(RuntimeException){return true;}
};
$db=new C2TDb();$GLOBALS['wpdb']=$db;$repo=new OperationsRepository();
foreach([false,0,1] as $result){
    $db->results=[$result];
    $error=$reject(static fn()=>$repo->markEventPublished('E1'));
    $test('Published status '.$result.' must match durable row change',
        $result===1 ? !$error : $error);
}
foreach([false,0,1] as $result){
    $db->results=[$result];
    $error=$reject(static fn()=>$repo->markEventRetry('E1',1,new DateTimeImmutable('2026-10-21T06:00:00Z')));
    $test('Retry status '.$result.' must match durable row change',
        $result===1 ? !$error : $error);
}
$event=['event_uuid'=>'CF02-EVT-TEST','publish_attempts'=>0];
$worker=new RuntimeWorker($repo,(new ReflectionClass(DataCipher::class))->newInstanceWithoutConstructor());
foreach([false,0] as $result){
    $db->rows=[$event];$db->results=[$result,1];$db->updates=[];$GLOBALS['cf02_test_event_actions']=[];
    $actual=$worker->processEvents(1);
    $test('No false published count on failed status update '.$result,
        $actual['published']===0 && $actual['retried']===1 && $actual['processed']===1);
    $test('No post-publish event hook on missing status persistence '.$result,
        !in_array('cf02_domain_event_published',$GLOBALS['cf02_test_event_actions'],true));
    $test('Retry is persisted once on missing publish persistence '.$result,
        count($db->updates)===2 && ($db->updates[1]['fields']['publish_state']??'')==='retry');
}
$db->rows=[$event];$db->results=[1];$db->updates=[];$GLOBALS['cf02_test_event_actions']=[];
$actual=$worker->processEvents(1);
$test('Durable status permits published count',
    $actual['published']===1 && $actual['retried']===0);
$test('Durable status permits action hook',
    in_array('cf02_domain_event_published',$GLOBALS['cf02_test_event_actions'],true));

$ops=(string)file_get_contents(dirname(__DIR__).'/src/Infrastructure/WordPress/OperationsRepository.php');
$overlay=(string)file_get_contents(dirname(__DIR__).'/src/Infrastructure/WordPress/CompleteRestOverlay.php');
$test('Database list failures throw LogicException outside input-error catch',
    str_contains($ops,"throw new LogicException('Support database list query failed.')")
    && str_contains($ops,"throw new LogicException('Support database list query returned malformed rows.')")
    && is_subclass_of(LogicException::class,RuntimeException::class)===false);
$test('REST overlay classifies RuntimeException input errors separately',
    strpos($overlay,'catch(RuntimeException $error)')<strpos($overlay,'catch(Throwable $error)'));
$test('REST overlay has safe server-unavailable response',
    str_contains($overlay,"'cf02_query_unavailable'")&&str_contains($overlay,"['status'=>503"));
printf("C2-T event status and DB status regression: %d/%d passed; %d failures.\n",
    $checks-count($failed),$checks,count($failed));
exit($failed===[]?0:1);
