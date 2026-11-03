<?php

use App\Domain\Availability\Services\DemandSourceRegistry;
use App\Domain\Availability\Services\VirtualStockDemandResolver;

it('registers the virtual stock demand resolver', function () {
    $registry = app(DemandSourceRegistry::class);

    expect($registry->has('virtual_stock_intake'))->toBeTrue()
        ->and($registry->resolve('virtual_stock_intake'))->toBeInstanceOf(VirtualStockDemandResolver::class);
});
