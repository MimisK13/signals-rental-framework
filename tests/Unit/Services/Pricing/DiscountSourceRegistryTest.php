<?php

use App\Contracts\Pricing\DiscountSource;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Services\Pricing\DiscountSourceRegistry;
use Carbon\CarbonInterface;

it('registers discount sources by key', function () {
    $registry = new DiscountSourceRegistry;

    $source = new class implements DiscountSource
    {
        public function key(): string
        {
            return 'plugin_volume';
        }

        public function discountPercentForItem(
            OpportunityItem $item,
            Opportunity $opportunity,
            CarbonInterface $at,
        ): string {
            return '10.00';
        }
    };

    $registry->register($source);

    expect($registry->all())->toHaveKey('plugin_volume')
        ->and($registry->all()['plugin_volume'])->toBe($source);
});
