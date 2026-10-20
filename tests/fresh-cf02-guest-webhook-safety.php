<?php
declare(strict_types=1);

/**
 * Fresh 2026-10-21 CF-02 runtime regressions. Synthetic input only.
 * These tests assert observable guest safety and webhook channel semantics.
 */
require_once dirname(__DIR__) . '/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__) . '/src');

class WP_REST_Request {
    public function __construct(private array $params = []) {}
    public function get_param(string $name): mixed { return $this->params[$name] ?? null; }
}
class WP_REST_Response {
    public function __construct(private array $body, private int $status = 200) {}
    public function get_status(): int { return $this->status; }
    public function get_data(): array { return $this->body; }
}
class WP_Error {
    public function __construct(public string $code, public string $message, public array $data = []) {}
}
function __(string $text, string $domain): string { return $text; }
function wp_salt(string $scheme): string { return 'synthetic-unit-test-salt'; }
function get_transient(string $key): int|false { return $GLOBALS['rate_transients'][$key] ?? false; }
function set_transient(string $key, int $value, int $ttl): bool {
    if ($GLOBALS['force_write_failure'] ?? false) { return false; }
    $GLOBALS['rate_transients'][$key] = $value;
    return true;
}
class SyntheticRateDb {
    public bool $deny = false;
    public array $queries = [];
    public function prepare(string $sql, mixed ...$args): string { return $sql; }
    public function get_var(string $sql): mixed {
        $this->queries[] = $sql;
        if (str_contains($sql, 'GET_LOCK')) { return $this->deny ? '0' : '1'; }
        if (str_contains($sql, 'RELEASE_LOCK')) { return '1'; }
        return false;
    }
}
$wpdb = new SyntheticRateDb();
$GLOBALS['rate_transients'] = [];
$GLOBALS['force_write_failure'] = false;
$_SERVER['REMOTE_ADDR'] = '192.0.2.15';

$errors = [];
$checks = 0;
$run = static function(string $name, callable $test) use (&$checks,&$errors):void {
    $checks++;
    try { if ($test() !== true) { throw new RuntimeException('Expected invariant not met'); }
        echo 'PASS '.$checks.' '.$name."\n";
    } catch (Throwable $e) { $errors[]=$name.': '.$e->getMessage(); fwrite(STDERR, 'FAIL '.$name.': '.$e->getMessage()."\n"); }
};
$guestRef = new ReflectionClass(\Sabri\CF02\Infrastructure\WordPress\GuestIntakeController::class);
$guest = $guestRef->newInstanceWithoutConstructor();
$limit = $guestRef->getMethod('consumeRateLimit');
$limit->setAccessible(true);
$webhookRef = new ReflectionClass(\Sabri\CF02\Infrastructure\WordPress\ProviderWebhookController::class);
$channel = $webhookRef->getMethod('canonicalMessageChannel');
$channel->setAccessible(true);
$normal = ['category'=>'technical','subject'=>'Ordinary support request','description'=>'Need assistance with access'];
$emergency = ['category'=>'technical','subject'=>'Medical emergency heart attack','description'=>'Need immediate assistance'];

$run('Normal guest requests are limited to ten per bucket',static function() use($limit,$guest):bool{
    $GLOBALS['rate_transients']=[];
    for($i=0;$i<10;$i++) if($limit->invoke($guest)!==true)return false;
    return $limit->invoke($guest)===false;
});
$run('No request is green when advisory lock is unavailable',static function()use($limit,$guest,$wpdb):bool{
    $wpdb->deny=true; $ok=$limit->invoke($guest)===false; $wpdb->deny=false; return $ok;
});
$run('Rate-limit persistence failure is fail-closed',static function()use($limit,$guest):bool{
    $GLOBALS['rate_transients']=[];$GLOBALS['force_write_failure']=true;
    $ok=$limit->invoke($guest)===false;
    $GLOBALS['force_write_failure']=false;return $ok;
});
$run('A successful rate-limit check releases DB lock',static function()use($limit,$guest,$wpdb):bool{
    $wpdb->queries=[];$GLOBALS['rate_transients']=[];
    $ok=$limit->invoke($guest);
    return $ok===true&&count(array_filter($wpdb->queries,static fn($q)=>str_contains($q,'GET_LOCK')))>0
        &&count(array_filter($wpdb->queries,static fn($q)=>str_contains($q,'RELEASE_LOCK')))>0;
});
$run('Rate exhaustion does not suppress an emergency diversion',static function()use($guest,$guestRef,$emergency):bool{
    $GLOBALS['rate_transients']=[];
    $request = new WP_REST_Request($emergency);
    $result=$guest->issueContinuation($request);
    return $result instanceof WP_REST_Response && $result->get_status()===422
       && $result->get_data()['runbook_type']==='clinical_red_flag'
       && $result->get_data()['ordinary_support_sla_applies']===false;
});
$run('Ordinary guest intake is still throttled',static function()use($guest,$normal):bool{
    $GLOBALS['force_write_failure']=true;
    $result=$guest->issueContinuation(new WP_REST_Request($normal));
    $GLOBALS['force_write_failure']=false;
    return $result instanceof WP_Error && $result->code==='cf02_guest_rate_limited'
        && ($result->data['status']??0)===429;
});
$run('Signed webhook ingress is normalized to internal system channel',static fn()=> $channel->invoke(null,'webhook')==='system');
$run('Email channel is preserved',static fn()=> $channel->invoke(null,'email')==='email');
$run('Native system channel is preserved',static fn()=> $channel->invoke(null,'system')==='system');
$run('Web channel is preserved',static fn()=> $channel->invoke(null,'web')==='web');
$run('Unknown inbound channel is rejected',static function()use($channel):bool{
    try {$channel->invoke(null,'private_message');return false;}catch(Throwable $e){return $e instanceof RuntimeException || $e->getPrevious() instanceof RuntimeException;}
});
$run('Webhook canonical channel is accepted by message persistence allowlist',static function()use($channel):bool{
    $operations=file_get_contents(dirname(__DIR__).'/src/Infrastructure/WordPress/OperationsRepository.php');
    return in_array($channel->invoke(null,'webhook'),['web','email','chat','system'],true)
        && str_contains((string)$operations, "['web', 'email', 'chat', 'system']");
});
$run('Emergency rate guard is after classifier in source execution order',static function():bool{
    $source=file_get_contents(dirname(__DIR__).'/src/Infrastructure/WordPress/GuestIntakeController.php');
    $part=explode('public function issueContinuation',$source,2)[1];
    $part=explode('public function continueCase',$part,2)[0];
    $classify=strpos($part,'EmergencyRunbookRegistry::classifyText');
    $rate=strpos($part,'$this->consumeRateLimit()');
    return $classify!==false&&$rate!==false&&$classify<$rate;
});
printf("CF-02 fresh security regressions: %d/%d passed; %d defects remain in this suite.\n",$checks-count($errors),$checks,count($errors));
exit($errors===[]?0:1);
