<?php
declare(strict_types=1);

/**
 * C2-P adversarial signed native-result callback checks.
 * Synthetic, independent of WordPress and real provider acceptance.
 */
class WP_REST_Request {
    public function __construct(private array $headers=[],private mixed $body='') {}
    public function get_header(string $name):string { return (string)($this->headers[strtolower($name)]??''); }
    public function get_body():mixed { return $this->body; }
}
function apply_filters(string $tag,mixed $initial,mixed ...$extra):mixed {
    if ($tag!=='cf02_provider_signing_key')return $initial;
    return $extra[0]==='test-key-01' && $extra[1]==='native_result'
        ? str_repeat('s',32):'';
}
require_once dirname(__DIR__) . '/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__) . '/src');

use Sabri\CF02\Integration\NativeResultVersion;
use Sabri\CF02\Infrastructure\WordPress\ProviderWebhookController;
$fail=[];
$cases=0;
$test=static function(string $label,callable $assert)use(&$fail,&$cases):void {
    ++$cases;
    try{
        if($assert()!==true)throw new RuntimeException('Invariant returned false');
        fwrite(STDOUT, sprintf("PASS %02d %s\n",$cases,$label));
    }catch(Throwable $e){
        $fail[]=$label;
        fwrite(STDERR,sprintf("FAIL %02d %s: %s\n",$cases,$label,$e->getMessage()));
    }
};
$accept=static fn(mixed $raw,int $version):bool => NativeResultVersion::fromPayload($raw)===$version;
$reject=static function(mixed $raw):bool {
    try{NativeResultVersion::fromPayload($raw);}catch(RuntimeException){return true;}
    return false;
};

foreach([
 ['min integer',1,1],['normal integer',42,42],
 ['min canonical string','1',1],['normal canonical string','42',42],
 ['large valid decimal string',(string)PHP_INT_MAX,PHP_INT_MAX],
 ['max valid integer',PHP_INT_MAX,PHP_INT_MAX],
] as [$label,$val,$expected]){
    $test("Version accepts $label",static fn()=> $accept($val,$expected));
}
$rejectValues=[
 ['missing null',null],
 ['false boolean',false],['true boolean',true],
 ['float 1.0',1.0],['fractional float',1.25],
 ['zero int',0],['negative int',-1],
 ['zero string','0'],['signed negative','-1'],['signed positive','+1'],
 ['zero prefixed','001'],['negative zero','-0'],
 ['leading blank',' 1'],['trailing blank',"1 "],
 ['scientific notation','1e2'],['hex string','0x10'],['decimal dot','1.0'],
 ['newline',"1\n"],['carriage return',"1\r"],['NUL',"1\0"],['tab',"\t1"],
 ['empty string',''],['digits after letter','1a'],['unbroken plus','+0'],
 ['32 digit overflow',str_repeat('9',32)],
 ['max plus zero',(string)PHP_INT_MAX.'0'],
 ['max plus one',(static function(){ $max=(string)PHP_INT_MAX;return substr($max,0,-1).(string)((int)substr($max,-1)+1);})()],
 ['array',[]],['object',(object)['x'=>1]],['nested array',['1']],
 ['unicode number','١'],['unicode plus','＋1'],['fully wide number','１'],
 ['minimum negative PHP',PHP_INT_MIN],['NaN',NAN],['infinite',INF],
];
foreach($rejectValues as [$label,$value])$test("Version rejects $label",static fn()=> $reject($value));

$ref=new ReflectionClass(ProviderWebhookController::class);
$verify=$ref->getMethod('verifySignature');
$verify->setAccessible(true);
$controller=$ref->newInstanceWithoutConstructor();
$signed=static function(string $body,string $timestamp, string $sig='',string $keyId='test-key-01'): WP_REST_Request{
    $sig=$sig===''?hash_hmac('sha256',$timestamp.'.'.$body,str_repeat('s',32)):$sig;
    return new WP_REST_Request([
        'x-cf02-timestamp'=>$timestamp,
        'x-cf02-key-id'=>$keyId,
        'x-cf02-signature'=>$sig
    ],$body);
};
$verifyAccept=static function(WP_REST_Request $req)use($controller,$verify):bool {
    try{$verify->invoke($controller,$req,'native_result');return true;}catch(Throwable){return false;}
};
$verifyReject=static fn(WP_REST_Request $req):bool=>!$verifyAccept($req);
$time=(string)time();
$test('Signed body accepted at 1 byte', static fn()=> $verifyAccept($signed('x',$time)));
$test('Signed body accepted at 65536 bytes', static fn()=> $verifyAccept($signed(str_repeat('x',65536),$time)));
$test('Signed body rejected at 65537 bytes', static fn()=> $verifyReject($signed(str_repeat('x',65537),$time)));
$test('Provider signature rejects malformed HMAC', static fn()=> $verifyReject($signed('x',$time,str_repeat('0',64))));
$test('Provider signature rejects absent known signing key', static fn()=> $verifyReject($signed('x',$time,'','unknown-key')));
$test('Provider signature rejects stale timestamp',static fn()=> $verifyReject($signed('x',(string)(time()-301))));
$test('Provider signature rejects future timestamp',static fn()=> $verifyReject($signed('x',(string)(time()+301))));
$test('Provider signature rejects malformed header timestamp',static fn()=> $verifyReject($signed('x','notatime')));
$test('Provider signature rejects a nonstring payload',static function()use($signed,$verifyReject,$time):bool {
    $req=$signed('x',$time);
    return $verifyReject(new WP_REST_Request([
        'x-cf02-timestamp'=>$time,
        'x-cf02-key-id'=>'test-key-01',
        'x-cf02-signature'=>hash_hmac('sha256',$time.'.x',str_repeat('s',32))
    ],['malformed'=>'array']));
});
$test('Native callback uses strict nonclamping parser',static function():bool{
    $src=(string)file_get_contents(dirname(__DIR__).'/src/Infrastructure/WordPress/ProviderWebhookController.php');
    return str_contains($src,"NativeResultVersion::fromPayload($"."payload['native_version'] ?? null)");
});
$test('Provider error hooks cannot expose throwable instances',static function():bool{
    $src=(string)file_get_contents(dirname(__DIR__).'/src/Infrastructure/WordPress/ProviderWebhookController.php');
    return !str_contains($src,"'error' => $"."error,");
});
printf("C2-P signed native result cases: %d/%d passed; %d failed; real provider proof not supplied.\n",
    $cases-count($fail),$cases,count($fail));
exit($fail===[]?0:1);
