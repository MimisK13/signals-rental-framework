<?php

namespace App\Services\Shortages;

use Carbon\CarbonInterface;

/**
 * Half-open day overlap between opportunity rental windows and intake availability.
 *
 * Both windows use availability semantics: [start, end) — adjacent windows do not
 * overlap. Day count is the number of UTC calendar days touched by the overlap
 * interval, counting from the overlap start's calendar day while strictly before
 * the overlap end instant.
 */
class IntakeOverlapDays
{
    public function count(
        CarbonInterface $intakeFrom,
        CarbonInterface $intakeUntil,
        CarbonInterface $opportunityFrom,
        CarbonInterface $opportunityUntil,
    ): int {
        $overlapStart = $intakeFrom->greaterThan($opportunityFrom) ? $intakeFrom : $opportunityFrom;
        $overlapEnd = $intakeUntil->lessThan($opportunityUntil) ? $intakeUntil : $opportunityUntil;

        if ($overlapEnd->lessThanOrEqualTo($overlapStart)) {
            return 0;
        }

        $cursor = $overlapStart->copy()->utc()->startOfDay();
        $end = $overlapEnd->copy()->utc();
        $days = 0;

        while ($cursor->lessThan($end)) {
            $days++;
            $cursor = $cursor->addDay();
        }

        return $days;
    }
}
