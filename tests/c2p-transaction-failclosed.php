<?php
declare(strict_types=1);
if (!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');
require_once dirname(__DIR__).'/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');

use Sabri\CF02\Infrastructure\WordPress\OperationsRepository;
use Sabri\CF02\Infrastructure\WordPress\IntakeRepository;
use Sabri\CF02\Infrastructure\WordPress\CaseRepository;

final class TxFailureFakeDb {
    public string $prefix='wp_';
    public bool $beginFailure=false;
    public bool $commitFailure=false;
    public array $calls=[];
    public function query(string $sql): int|false {
        $this->calls[]=$sql;
        if ($sql==='START TRANSACTION'&&$this->beginFailure)return false;
        if ($sql==='COMMIT'&&$this->commitFailure)return false;
        return 0; // MySQL successful START/COMMIT/ROLLBACK often report zero affected rows
    }
    public function prepare(string $query,mixed ...$args):string { return $query; }
    public function get_row(string $query,string $mode):?array { return null; }
}
$checks=0;$fail=[];
$test=static function(string $name,callable $assert)use(&$checks,&$fail):void{
    ++$checks;
    try{
        if($assert()!==true)throw new RuntimeException('Assertion did not pass');
        echo "PASS $checks $name\n";
    }catch(Throwable $e){$fail[]=$name;fwrite(STDERR,"FAIL $checks $name: ".$e->getMessage()."\n");}
};
$build=static function(TxFailureFakeDb $db):array {
    global $wpdb; $wpdb=$db;
    $opsRef=new ReflectionClass(OperationsRepository::class);
    $ops=$opsRef->newInstanceWithoutConstructor();
    $opsRef->getProperty('wpdb')->setValue($ops,$db);
    $method=$opsRef->getMethod('transaction');$method->setAccessible(true);
    return [$ops,$method];
};
$throws=static function(callable $fn):bool{
    try{$fn();return false;}catch(RuntimeException){return true;}
};
$test('No callback runs without a successful transaction begin',static function()use($build,$throws):bool{
    $db=new TxFailureFakeDb();$db->beginFailure=true;[$ops,$method]=$build($db);$executed=false;
    $denied=$throws(static function()use($method,$ops,&$executed):void{
        $method->invoke($ops,static function()use(&$executed):void{$executed=true;});
    });
    return $denied&&!$executed&&$db->calls===['START TRANSACTION'];
});
$test('Failed commit cannot report a successful operation',static function()use($build,$throws):bool{
    $db=new TxFailureFakeDb();$db->commitFailure=true;[$ops,$method]=$build($db);$executed=false;
    $denied=$throws(static function()use($method,$ops,&$executed):void{
        $method->invoke($ops,static function()use(&$executed):void{$executed=true;});
    });
    return $denied&&$executed&&$db->calls===['START TRANSACTION','COMMIT','ROLLBACK'];
});
$test('Successful commit preserves expected normal behavior',static function()use($build):bool{
    $db=new TxFailureFakeDb();[$ops,$method]=$build($db);$called=0;
    $method->invoke($ops,static function()use(&$called):void{$called++;});
    return $called===1&&$db->calls===['START TRANSACTION','COMMIT'];
});
$test('Callback failure rolls back and propagates',static function()use($build,$throws):bool{
    $db=new TxFailureFakeDb();[$ops,$method]=$build($db);
    $denied=$throws(static function()use($method,$ops):void{
        $method->invoke($ops,static function():void{throw new RuntimeException('Synthetic failure');});
    });
    return $denied&&$db->calls===['START TRANSACTION','ROLLBACK'];
});
$test('Intake fails before record creation when transaction unavailable',static function()use($throws):bool{
    global $wpdb;$wpdb=new TxFailureFakeDb();$wpdb->beginFailure=true;
    $repo=new IntakeRepository(new CaseRepository());
    $denied=$throws(static function()use($repo):void{
        $repo->createOrReplay('user:tx-test','CF02-TX-GUARD-123456789',[
            'category'=>'technical','priority'=>'P3','severity'=>'normal',
            'queue'=>'technical_support','locale'=>'en-US','subject'=>'Transaction failed',
        ],new DateTimeImmutable('2026-10-21T00:00:00+00:00'));
    });
    return $denied&&$wpdb->calls===['START TRANSACTION'];
});
$test('Both intake commit paths require explicit success',static function():bool{
    $code=(string)file_get_contents(dirname(__DIR__).'/src/Infrastructure/WordPress/IntakeRepository.php');
    return str_contains($code,'Intake replay commit failed; outcome is unverified.')
       && str_contains($code,'Intake creation commit failed; outcome is unverified.')
       && str_contains($code,"query('COMMIT') === false");
});
printf("CF-02 transaction failclosed cases: %d/%d pass; %d fail.\n",$checks-count($fail),$checks,count($fail));
exit($fail===[]?0:1);
