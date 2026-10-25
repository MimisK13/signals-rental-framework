<?php

namespace App\Contracts;

use App\Services\Availability\DemandReadContext;
use App\Services\Availability\StockReadContext;
use App\ValueObjects\ResolutionOption;
use App\ValueObjects\Shortage;

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
