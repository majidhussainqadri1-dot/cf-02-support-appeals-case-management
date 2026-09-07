<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use Sabri\CF02\Governance\SupportParityAudit;
use Throwable;

final class MonthlyParityAuditRunner
{
    public const OPTION_LAST_AUDIT = 'cf02_last_support_parity_audit';

    /** @return array<string,mixed> */
    public static function run(?string $month = null): array
    {
        $month ??= gmdate('Y-m', strtotime('first day of last month 00:00:00 UTC'));
        /** @var mixed $source */
        $source = apply_filters('cf02_support_parity_aggregate_source', null, $month);
        if (!is_array($source) || !isset($source['donor'], $source['non_donor'])
            || !is_array($source['donor']) || !is_array($source['non_donor'])) {
            $result = [
                'month' => $month,
                'status' => 'unknown',
                'reason' => 'approved_aggregate_source_unavailable',
                'aggregate_only' => true,
                'routing_consumes_donor_signal' => false,
                'audit_hash' => hash('sha256', $month . '|source-unavailable'),
            ];
        } else {
            try {
                $result = (new SupportParityAudit())->evaluate($month, $source['donor'], $source['non_donor']);
            } catch (Throwable $error) {
                $result = [
                    'month' => $month,
                    'status' => 'unknown',
                    'reason' => 'aggregate_source_invalid',
                    'aggregate_only' => true,
                    'routing_consumes_donor_signal' => false,
                    'audit_hash' => hash('sha256', $month . '|invalid|' . $error::class),
                ];
            }
        }
        update_option(self::OPTION_LAST_AUDIT, $result, false);
        if (($result['status'] ?? 'unknown') === 'blocker') {
            do_action('cf02_release_blocker', 'donor_non_donor_support_parity', $result);
        }
        do_action('cf02_support_parity_audit_completed', $result);
        return $result;
    }
}
