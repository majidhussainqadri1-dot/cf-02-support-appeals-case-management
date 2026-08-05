<?php

declare(strict_types=1);

namespace Sabri\CF02\Feedback;

use InvalidArgumentException;

final class SatisfactionAggregator
{
    /** @param list<SatisfactionFeedback> $feedback */
    public function aggregate(array $feedback, int $minimumCohort = 5): array
    {
        if ($minimumCohort < 3 || $minimumCohort > 100) {
            throw new InvalidArgumentException('Privacy cohort threshold must be between 3 and 100.');
        }
        $ratings = [];
        $respondents = [];
        $optOuts = 0;
        foreach ($feedback as $item) {
            if (!$item instanceof SatisfactionFeedback) {
                throw new InvalidArgumentException('Feedback corpus is malformed.');
            }
            if ($item->optedOut()) {
                ++$optOuts;
                continue;
            }
            $ratings[] = (int) $item->rating();
            $respondents[$item->respondentPseudonym()] = true;
        }
        if (count($respondents) < $minimumCohort) {
            return [
                'suppressed' => true,
                'reason' => 'Cohort is below the privacy threshold.',
                'response_count' => null,
                'average_rating' => null,
                'opt_out_count' => null,
            ];
        }
        return [
            'suppressed' => false,
            'reason' => 'Privacy cohort threshold satisfied.',
            'response_count' => count($ratings),
            'average_rating' => $ratings === [] ? null : round(array_sum($ratings) / count($ratings), 2),
            'opt_out_count' => $optOuts,
        ];
    }
}
