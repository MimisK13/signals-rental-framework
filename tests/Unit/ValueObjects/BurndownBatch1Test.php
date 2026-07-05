<?php

use App\Enums\ShortageDispatchPolicy;
use App\Enums\StockMethod;
use App\Models\OpportunityItem;
use App\ValueObjects\DispatchGateResult;
use App\ValueObjects\Shortage;
use App\ValueObjects\ShortageCollection;
use App\ValueObjects\ShortageGroup;
use Illuminate\Support\Carbon;

describe('ShortageGroup::isCrossOpportunity', function () {
    it('is true when shortages span two opportunities', function () {
        $windowStart = Carbon::parse('2026-08-01T09:00:00Z');
        $windowEnd = Carbon::parse('2026-08-05T17:00:00Z');

        $group = new ShortageGroup(
            productId: 10,
            productName: 'Cable',
            storeId: 1,
            dateRangeStart: $windowStart,
            dateRangeEnd: $windowEnd,
            totalShortfall: 3,
            shortages: [
                Shortage::make(
                    opportunityItemId: 11,
                    opportunityId: 1,
                    productId: 10,
                    productName: 'Cable',
                    storeId: 1,
                    requestedQuantity: 2,
                    availableQuantity: 0,
                    trackingType: StockMethod::Bulk,
                    startsAt: $windowStart,
                    endsAt: $windowEnd,
                    isCritical: false,
                ),
                Shortage::make(
                    opportunityItemId: 12,
                    opportunityId: 2,
                    productId: 10,
                    productName: 'Cable',
                    storeId: 1,
                    requestedQuantity: 1,
                    availableQuantity: 0,
                    trackingType: StockMethod::Bulk,
                    startsAt: $windowStart,
                    endsAt: $windowEnd,
                    isCritical: false,
                ),
            ],
        );

        expect($group->isCrossOpportunity())->toBeTrue()
            ->and($group->opportunityCount())->toBe(2);
    });
});

describe('DispatchGateResult::clampBulkDispatchQuantity', function () {
    it('returns the requested quantity when the line is not in the shortage set', function () {
        $item = new OpportunityItem([
            'id' => 99,
            'dispatched_quantity' => '0.00',
        ]);

        $shortages = new ShortageCollection([
            Shortage::make(
                opportunityItemId: 1,
                opportunityId: 1,
                productId: 5,
                productName: 'Other',
                storeId: 1,
                requestedQuantity: 3,
                availableQuantity: 1,
                trackingType: StockMethod::Bulk,
                startsAt: Carbon::parse('2026-08-01T09:00:00Z'),
                endsAt: Carbon::parse('2026-08-05T17:00:00Z'),
                isCritical: false,
            ),
        ]);

        $result = new DispatchGateResult(ShortageDispatchPolicy::WarnPartial, $shortages);

        expect($result->clampBulkDispatchQuantity($item, '2.00'))->toBe('2.00');
    });
});
