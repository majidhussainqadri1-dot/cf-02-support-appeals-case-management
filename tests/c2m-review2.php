<?php

declare(strict_types=1);
ini_set('assert.exception','1');assert_options(ASSERT_ACTIVE,1);assert_options(ASSERT_EXCEPTION,1);
require_once dirname(__DIR__).'/src/Autoload.php';\Sabri\CF02\Autoload::register(dirname(__DIR__).'/src');
use Sabri\CF02\Future\FeatureCatalog;

$failures=[];$test=static function(string $n,callable $c)use(&$failures):void{try{$c();fwrite(STDOUT,"PASS {$n}\n");}catch(Throwable $e){$failures[]=$n.': '.$e->getMessage();fwrite(STDERR,"FAIL {$n}: {$e->getMessage()}\n");}};
$root=dirname(__DIR__);

$test('Review2 all twenty-four FUT IDs exist exactly in catalog order',static function():void{
    $ids=array_keys(FeatureCatalog::all());assert(count($ids)===24);foreach(range(1,24) as $index){assert($ids[$index-1]===sprintf('CF02-FUT-%03d',$index));}
});

$test('Review2 Future namespace is pure domain code with no direct WordPress persistence or network side effects',static function()use($root):void{
    $files=glob($root.'/src/Future/*.php');assert(is_array($files)&&count($files)===8);
    foreach($files as $file){$source=(string)file_get_contents($file);foreach(['$wpdb','update_option(','delete_option(','wp_remote_','register_rest_route(','add_action(','add_filter(','curl_exec(','file_put_contents('] as $forbidden){assert(!str_contains($source,$forbidden),basename($file).' contains direct runtime side effect '.$forbidden);}}
});

$test('Review2 security primitives and local-only deep-link boundary are permanent source gates',static function()use($root):void{
    $source=(string)file_get_contents($root.'/src/Future/IntegrationSecurityIntelligence.php');
    assert(str_contains($source,"hash_hmac('sha256'"));assert(str_contains($source,'hash_equals('));assert(str_contains($source,"str_starts_with(\$relativePath,'/')"));assert(str_contains($source,"str_starts_with(\$relativePath,'//')"));
});

$test('Review2 future catalog preserves canonical-owner and activation boundaries',static function():void{
    foreach(FeatureCatalog::all() as $feature){assert($feature['activation']==='feature-gated');assert(str_contains($feature['owner'],'CF-02')||str_contains($feature['owner'],'identity/preferences'));assert(trim($feature['guardrail'])!=='');}
});

$test('Review2 no schema or public contract fork is introduced by Future24 foundations',static function()use($root):void{
    $catalog=(string)file_get_contents($root.'/src/Future/FeatureCatalog.php');$schema=(string)file_get_contents($root.'/src/Infrastructure/WordPress/SchemaCompletion.php');$contract=(string)file_get_contents($root.'/src/Contracts/SupportContractCatalog.php');
    assert(!str_contains($catalog,'CREATE TABLE'));assert(str_contains($schema,"public const VERSION = '1.3.0';"));assert(str_contains($contract,"public const CONTRACT_VERSION = '1.1.0';"));
});

if($failures!==[])exit(1);fwrite(STDOUT,"CF-02 C2-M Future24 fresh adversarial Review 2 passed.\n");
