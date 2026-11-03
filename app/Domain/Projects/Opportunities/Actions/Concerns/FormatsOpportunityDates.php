<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions\Concerns;

use Illuminate\Support\Carbon;

/**
 * Shared date-to-ISO-8601 formatting helper for the opportunity actions that bake
 * concrete window dates into their firing events (add/change item, clone, version).
 */
trait FormatsOpportunityDates
{
    /**
     * Format a date value as an ISO-8601 string, preserving null.
     */
    protected function toIso(?\DateTimeInterface $value): ?string
    {
        return $value !== null ? Carbon::parse($value)->toIso8601String() : null;
    }
}
