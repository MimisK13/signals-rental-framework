<?php

declare(strict_types=1);

namespace App\Domain\Availability\Shortages\Contracts;

use App\Domain\Availability\Services\DemandReadContext;
use App\Domain\Availability\Services\StockReadContext;
use App\Domain\Availability\Shortages\ValueObjects\ResolutionOption;
use App\Domain\Availability\Shortages\ValueObjects\Shortage;

/**
 * Optional capability for resolvers that probe availability while building options.
 */
interface StockContextAwareShortageResolver
{
    /**
     * @return list<ResolutionOption>
     */
    public function getOptionsWithStockContext(
        Shortage $shortage,
        StockReadContext $stockContext,
        ?DemandReadContext $demandContext = null,
    ): array;
}
