<?php

declare(strict_types=1);

namespace App\Domain\Availability\Shortages\Contracts;

use App\Domain\Availability\Shortages\ValueObjects\ResolutionOption;
use App\Domain\Availability\Shortages\ValueObjects\ResolutionResult;
use App\Domain\Availability\Shortages\ValueObjects\ShortageGroup;

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
