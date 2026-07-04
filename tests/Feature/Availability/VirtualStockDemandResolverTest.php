<?php

use App\Services\Availability\VirtualStockDemandResolver;
use App\Services\DemandSourceRegistry;

it('registers the virtual stock demand resolver', function () {
    $registry = app(DemandSourceRegistry::class);

    expect($registry->has('virtual_stock_intake'))->toBeTrue()
        ->and($registry->resolve('virtual_stock_intake'))->toBeInstanceOf(VirtualStockDemandResolver::class);
});
