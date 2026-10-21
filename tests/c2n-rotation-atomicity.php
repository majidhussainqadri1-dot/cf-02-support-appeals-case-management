<?php

declare(strict_types=1);
if (!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');
function apply_filters(string $name, mixed $default, mixed ...$args): mixed {
    return $name === 'cf02_key_rotation_enabled' ? true : $default;
}
function wp_json_encode(mixed $value): string|false { return json_encode($value); }
$GLOBALS['cf02_rotation_events'] = [];
function do_action(string $name, mixed ...$args): void {
    $GLOBALS['cf02_rotation_events'][] = $name;
}
require_once dirname(__DIR__) . '/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__) . '/src');

use Sabri\CF02\Security\DataCipher;
use Sabri\CF02\Security\ManagedKeyRing;
use Sabri\CF02\Infrastructure\WordPress\EncryptionRotationService;

final class RotationFakeDatabase
{
    public string $prefix = 'wp_';
    public ?string $ciphertext = null;
    public ?string $pending = null;
    public int $auditCount = 0;
    public bool $failAudit = false;
    public bool $failCommit = false;
    private bool $transaction = false;

    public function query(string $sql): int|false
    {
        if ($sql === 'START TRANSACTION') { $this->transaction = true; $this->pending = $this->ciphertext; return 0; }
        if ($sql === 'ROLLBACK') { $this->transaction = false; $this->pending = null; return 0; }
        if ($sql === 'COMMIT') {
            if ($this->failCommit) return false;
            $this->ciphertext = $this->pending;
            $this->pending = null;
            $this->transaction = false;
            return 0;
        }
        return false;
    }
    public function prepare(string $sql,mixed ...$args): string { return $sql; }
    public function get_results(string $sql,string $mode): array
    {
        if (str_contains($sql,'wp_cf02_messages') && $this->ciphertext !== null) {
            return [['row_id'=>1,'ciphertext'=>$this->ciphertext]];
        }
        return [];
    }
    public function update(string $table,array $data,array $where): int|false
    {
        if (!$this->transaction || !str_contains($table,'cf02_messages') || $where['body_ciphertext'] !== $this->ciphertext) return false;
        $this->pending = $data['body_ciphertext'];
        return 1;
    }
    public function insert(string $table,array $data): int|false
    {
        if ($this->failAudit) return false;
        if (!$this->transaction || !str_contains($table,'cf02_key_rotation')) return false;
        ++$this->auditCount;
        return 1;
    }
    public function get_var(string $sql): ?string { return $this->ciphertext; }
}

$old = new ManagedKeyRing('old-v1',['old-v1'=>str_repeat('o',32)],'test-provider','rotation-old-2026');
$new = new ManagedKeyRing('new-v2',[
    'old-v1'=>['material'=>str_repeat('o',32),'status'=>'retired'],
    'new-v2'=>['material'=>str_repeat('n',32),'status'=>'active'],
],'test-provider','rotation-new-2026');
$previous = (new DataCipher($old))->encrypt('sensitive content');
$cipher = new DataCipher($new);
$test = static function(string $label, bool $ok): void {
    fwrite($ok ? STDOUT : STDERR, ($ok ? 'PASS ' : 'FAIL ') . $label . "\n");
    if (!$ok) throw new RuntimeException($label);
};

$wpdb = new RotationFakeDatabase();
$wpdb->ciphertext = $previous;
$wpdb->failAudit = true;
$result = (new EncryptionRotationService($cipher))->rotate(1);
$test('Failed audit insert never counts as rotated', $result['rotated']===0 && $result['failed']===1);
$test('Failed audit insert rolls back encrypted row', $wpdb->ciphertext===$previous && $wpdb->auditCount===0);
$test('Failed audit emits both audit and item failure hooks',
    in_array('cf02_key_rotation_evidence_write_failed',$GLOBALS['cf02_rotation_events'],true)
    && in_array('cf02_key_rotation_item_failed',$GLOBALS['cf02_rotation_events'],true));

$wpdb = new RotationFakeDatabase();
$wpdb->ciphertext = $previous;
$result = (new EncryptionRotationService($cipher))->rotate(1);
$test('Committed key rotation has matching audit ledger', $result['rotated']===1&&$result['failed']===0&&$wpdb->auditCount===1);
$test('Committed ciphertext decrypts under active key',
    is_string($wpdb->ciphertext)&&str_starts_with($wpdb->ciphertext,'v2:new-v2:')
    && $cipher->decrypt($wpdb->ciphertext)==='sensitive content');
fwrite(STDOUT,"CF-02 key-rotation atomicity regressions passed.\n");
