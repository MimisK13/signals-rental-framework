<?php

use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Services\Opportunities\OpportunityLineItemsTreeBuilder;

it('exposes surcharge totals on line item editor rows', function () {
    $opportunity = Opportunity::factory()->create(['currency_code' => 'GBP']);
    OpportunityItem::factory()->for($opportunity)->create([
        'total' => 9000,
        'surcharge_total' => 450,
        'unit_price' => 10_000,
    ]);

    $rows = app(OpportunityLineItemsTreeBuilder::class)->tree($opportunity->fresh());

    expect($rows[0]['surcharge_total'])->toBe(450)
        ->and($rows[0]['surcharge_total_display'])->toBe('GBP 4.50')
        ->and($rows[0]['charge_breakdown']['surcharge_display'])->toBe('GBP 4.50');
});
