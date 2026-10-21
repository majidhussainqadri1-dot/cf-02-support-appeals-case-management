<?php

declare(strict_types=1);

/**
 * CF-02 / File 17 / File 19 outbox replay contract: forty distinct
 * authorized-reference/payload combinations. WPDB mock, NOT live delivery.
 */
function wp_json_encode(mixed $value, int $flags = 0): string|false {
    return json_encode($value, $flags);
}
if (!defined('ARRAY_A')) { define('ARRAY_A', 'ARRAY_A'); }
require_once dirname(__DIR__) . '/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__) . '/src');

use Sabri\CF02\Domain\SupportCaseId;
use Sabri\CF02\Infrastructure\WordPress\OperationsRepository;

final class C2WReplayDb {
    public string $prefix = 'wp_';
    public array $existing = [];
    public int $writes = 0;
    public function prepare(string $sql, mixed ...$args): string { return $sql; }
    public function get_row(string $sql, string $format): ?array { return $this->existing; }
    public function query(string $sql): int { $this->writes++; return 0; }
    public function insert(string $table, array $values): int { $this->writes++; return 1; }
}

$fail = [];
$total = 0;
$assertCase = static function(
    int $number,
    string $name,
    array $base,
    array $candidate,
    bool $expectReplay
) use (&$fail, &$total): void {
    $db = new C2WReplayDb();
    $db->existing = [
        'message_uuid' => 'CF02-OUT-EXISTING',
        'case_uuid' => $base['case_id']->value(),
        'template_key' => $base['template'],
        'recipient_ref' => $base['recipient'],
        'channel' => $base['channel'],
        'payload_hash' => hash('sha256', json_encode($base['payload'], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)),
    ];
    $GLOBALS['wpdb'] = $db;
    $repository = new OperationsRepository();
    $replayed = false; $rejected = false;
    try {
        $id = $repository->enqueueDelivery(
            $candidate['case_id'], $candidate['recipient'], $candidate['channel'],
            $candidate['template'], $candidate['payload'], 'encrypted-fixture',
            'same-key-as-original', new DateTimeImmutable('2026-10-21T07:00:00Z')
        );
        $replayed = $id === 'CF02-OUT-EXISTING';
    } catch (RuntimeException $e) {
        $rejected = $e->getMessage() === 'Delivery idempotency collision.';
    }
    $ok = $expectReplay ? $replayed && !$rejected : $rejected && !$replayed;
    $ok = $ok && $db->writes === 0;
    ++$total;
    if (!$ok) { $fail[] = $name; }
    fwrite($ok ? STDOUT : STDERR, sprintf("%s %02d/40 %s\n", $ok ? 'PASS' : 'FAIL', $number, $name));
};

for ($mask = 0; $mask < 32; ++$mask) {
    $base = [
        'case_id' => SupportCaseId::generate(),
        'recipient' => 'user:abc',
        'channel' => 'in_app',
        'template' => 'support_case_update',
        'payload' => ['case_ref' => 'case-A', 'notice' => 'Queued'],
    ];
    $candidate = $base;
    if ($mask & 1) { $candidate['case_id'] = SupportCaseId::generate(); }
    if ($mask & 2) { $candidate['template'] = 'support_case_resolved'; }
    if ($mask & 4) { $candidate['recipient'] = 'user:xyz'; }
    if ($mask & 8) { $candidate['channel'] = 'email'; }
    if ($mask & 16) { $candidate['payload'] = ['case_ref' => 'case-A', 'notice' => 'Changed']; }
    $assertCase($mask + 1, 'five-field change mask ' . $mask, $base, $candidate, $mask === 0);
}

// Eight extra legitimate replays, with different non-colliding bound identities.
for ($i = 0; $i < 8; ++$i) {
    $base = [
        'case_id' => SupportCaseId::generate(),
        'recipient' => 'doctor:'. $i,
        'channel' => $i % 2 === 0 ? 'email' : 'sms',
        'template' => 'notice_' . $i,
        'payload' => ['severity' => $i, 'localized' => $i % 2 === 0 ? 'اردو' : 'English'],
    ];
    $assertCase(33 + $i, 'exact replay for distinct template/recipient ' . $i, $base, $base, true);
}
if ($total !== 40) { $fail[] = 'Incorrect scenario count'; }
printf("C2-W cross-case and cross-template notification replay: %d/%d passed, %d failed; provider integration unverified.\n",
    $total - count($fail), $total, count($fail));
exit($fail === [] ? 0 : 1);
