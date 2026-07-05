<?php

namespace App\Contracts;

use App\ValueObjects\ResolutionOption;
use App\ValueObjects\ResolutionResult;
use App\ValueObjects\ShortageGroup;

/**
 * Optional batch-resolution seam for resolvers that can consolidate multiple
 * shortages into one side effect (shortage-resolution-sub-hires.md §3.5).
 */
interface BatchShortageResolverContract extends ShortageResolverContract
{
    public function supportsBatch(): bool;

    public function canResolveBatch(ShortageGroup $group): bool;

    /**
     * @return list<ResolutionOption>
     */
    public function getBatchOptions(ShortageGroup $group): array;

    public function applyBatch(ShortageGroup $group, ResolutionOption $option): ResolutionResult;
}
