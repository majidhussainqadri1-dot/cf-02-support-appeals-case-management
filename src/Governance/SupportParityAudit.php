<?php

declare(strict_types=1);

namespace Sabri\CF02\Governance;

use InvalidArgumentException;

/** Privacy-safe aggregate audit: donor identity never enters CF-02 case routing. */
final class SupportParityAudit
{
    /** @var array<string,float> */
    private const TOLERANCES = [
        'first_response_seconds_p50' => 0.10,
        'resolution_seconds_p50' => 0.10,
        'escalation_rate' => 0.05,
        'reopen_rate' => 0.05,
        'appeal_access_rate' => 0.05,
    ];

    /**
     * @param array{cohort_size:int,metrics:array<string,int|float>,source_version?:string} $donor
     * @param array{cohort_size:int,metrics:array<string,int|float>,source_version?:string} $nonDonor
     * @return array<string,mixed>
     */
    public function evaluate(string $month, array $donor, array $nonDonor, int $minimumCohort = 20): array
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1 || $minimumCohort < 10) {
            throw new InvalidArgumentException('Parity audit month or minimum cohort is invalid.');
        }
        foreach (['donor' => $donor, 'non_donor' => $nonDonor] as $name => $snapshot) {
            $extra = array_diff(array_keys($snapshot), ['cohort_size', 'metrics', 'source_version']);
            if ($extra !== [] || !isset($snapshot['cohort_size'], $snapshot['metrics'])
                || !is_int($snapshot['cohort_size']) || !is_array($snapshot['metrics'])) {
                throw new InvalidArgumentException(sprintf('Parity audit %s snapshot must be aggregate-only.', $name));
            }
            if (array_diff(array_keys($snapshot['metrics']), array_keys(self::TOLERANCES)) !== []
                || array_diff(array_keys(self::TOLERANCES), array_keys($snapshot['metrics'])) !== []) {
                throw new InvalidArgumentException(sprintf('Parity audit %s metrics are incomplete or unapproved.', $name));
            }
        }

        if ($donor['cohort_size'] < $minimumCohort || $nonDonor['cohort_size'] < $minimumCohort) {
            return $this->finish($month, 'suppressed', [], $donor['cohort_size'], $nonDonor['cohort_size'], 'minimum_privacy_cohort_not_met');
        }

        $variances = [];
        $blocked = false;
        foreach (self::TOLERANCES as $metric => $tolerance) {
            $left = $donor['metrics'][$metric] ?? null;
            $right = $nonDonor['metrics'][$metric] ?? null;
            if (!is_int($left) && !is_float($left) || !is_int($right) && !is_float($right) || $left < 0 || $right < 0) {
                throw new InvalidArgumentException('Parity audit metric values must be non-negative numbers.');
            }
            $delta = abs((float) $left - (float) $right);
            $relative = in_array($metric, ['first_response_seconds_p50', 'resolution_seconds_p50'], true)
                ? $delta / max(1.0, (float) $right)
                : $delta;
            $metricBlocked = $relative > $tolerance;
            $blocked = $blocked || $metricBlocked;
            $variances[$metric] = [
                'donor' => (float) $left,
                'non_donor' => (float) $right,
                'variance' => $relative,
                'tolerance' => $tolerance,
                'blocked' => $metricBlocked,
            ];
        }
        return $this->finish($month, $blocked ? 'blocker' : 'pass', $variances, $donor['cohort_size'], $nonDonor['cohort_size'], $blocked ? 'parity_variance_requires_investigation' : null);
    }

    /** @param array<string,mixed> $variances @return array<string,mixed> */
    private function finish(string $month, string $status, array $variances, int $donorSize, int $nonDonorSize, ?string $reason): array
    {
        $result = [
            'month' => $month,
            'status' => $status,
            'donor_cohort_size' => $donorSize,
            'non_donor_cohort_size' => $nonDonorSize,
            'variances' => $variances,
            'reason' => $reason,
            'aggregate_only' => true,
            'routing_consumes_donor_signal' => false,
        ];
        $result['audit_hash'] = hash('sha256', json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        return $result;
    }
}
