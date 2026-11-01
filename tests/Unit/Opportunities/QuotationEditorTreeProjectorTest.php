<?php

use App\Data\Planning\OpportunityItemPlanningData;
use App\Services\Opportunities\QuotationEditorTreeProjector;
use Illuminate\Support\Collection;

it('projects the exact experimental editor contract without changing row order', function (): void {
    $firstRow = canonicalQuotationRow(91, '0002');
    $secondRow = canonicalQuotationRow(37, '0001', 'group');
    $planning = quotationPlanningData(91);

    $projected = (new QuotationEditorTreeProjector)->project(
        [$firstRow, $secondRow],
        new Collection([91 => $planning]),
    );

    $rowFields = [
        'id',
        'item_type',
        'path',
        'depth',
        'parent_group_id',
        'name',
        'description',
        'notes',
        'quantity',
        'days',
        'unit_price',
        'unit_price_raw',
        'discount_percent',
        'is_discount_manual',
        'charge_total',
        'type_label',
        'charge_period_label',
        'status_label',
        'has_shortage',
        'is_optional',
        'product_id',
        'product_url',
        'charge_breakdown',
        'availability_url',
        'starts_at',
        'ends_at',
        'has_duplicates',
        'planning',
    ];
    $planningFields = [
        'requested_quantity',
        'reserved_quantity',
        'reservation_mode',
        'reservation_state',
        'available_quantity',
        'external_supply_quantity',
        'shortage_quantity',
        'has_shortage',
        'allocated_quantity',
    ];

    expect($projected)->toHaveCount(2)
        ->and(array_column($projected, 'id'))->toBe([91, 37])
        ->and(array_keys($projected[0]))->toBe($rowFields)
        ->and(array_keys($projected[0]['planning']))->toBe($planningFields)
        ->and($projected[1]['planning'])->toBeNull();

    foreach (array_diff($rowFields, ['planning']) as $field) {
        expect($projected[0][$field])->toBe($firstRow[$field]);
    }

    foreach ($planningFields as $field) {
        expect($projected[0]['planning'][$field])->toBe($planning->{$field});
    }
});

it('reduces the serialized payload by omitting canonical fields the editor recomputes or does not consume', function (): void {
    $row = canonicalQuotationRow(91, '0001');
    $planning = quotationPlanningData(91);
    $canonical = [[...$row, 'planning' => get_object_vars($planning)]];
    $projected = (new QuotationEditorTreeProjector)->project(
        [$row],
        new Collection([91 => $planning]),
    );

    $canonicalBytes = strlen(json_encode($canonical, JSON_THROW_ON_ERROR));
    $projectedBytes = strlen(json_encode($projected, JSON_THROW_ON_ERROR));

    expect($projectedBytes)->toBeLessThan($canonicalBytes)
        ->and($projected[0])->not->toHaveKeys([
            'unit_price_display',
            'charge_total_display',
            'has_children',
            'is_collapsed',
            'availability_status',
            'quantity_raw',
            'revenue_group_id',
            'surcharge_total',
            'surcharge_total_display',
        ])
        ->and($projected[0]['planning'])->not->toHaveKeys([
            'opportunity_item_id',
            'product_name',
            'store_name',
            'planning_starts_at',
            'planning_ends_at',
            'owned_available_quantity',
        ]);
});

it('keeps a representative 250-line snapshot within the payload budget', function (): void {
    $rows = [];
    $planning = [];

    foreach (range(1, 250) as $id) {
        $rows[] = canonicalQuotationRow($id, str_pad((string) $id, 4, '0', STR_PAD_LEFT));
        $planning[$id] = quotationPlanningData($id);
    }

    $projected = (new QuotationEditorTreeProjector)->project($rows, new Collection($planning));
    $bytes = strlen(json_encode($projected, JSON_THROW_ON_ERROR));

    expect($projected)->toHaveCount(250)
        ->and($bytes)->toBeLessThan(250_000);
});

/**
 * @return array<string, mixed>
 */
function canonicalQuotationRow(int $id, string $path, string $itemType = 'product'): array
{
    return [
        'id' => $id,
        'item_type' => $itemType,
        'path' => $path,
        'depth' => 1,
        'parent_path' => null,
        'parent_group_id' => null,
        'revenue_group_id' => null,
        'name' => 'Lighting package',
        'description' => 'Main package',
        'notes' => 'Load first',
        'quantity' => '2',
        'quantity_raw' => '2.00',
        'days' => 3,
        'unit_price' => 12500,
        'unit_price_display' => '£125.00',
        'unit_price_raw' => '125.00',
        'discount_percent' => '10.00',
        'is_discount_manual' => true,
        'charge_total' => 67500,
        'charge_total_display' => '£675.00',
        'surcharge_total' => 500,
        'surcharge_total_display' => '£5.00',
        'type_label' => 'Rental',
        'status_label' => 'Available',
        'availability_status' => 'available',
        'has_shortage' => false,
        'is_optional' => false,
        'is_collapsed' => false,
        'has_children' => true,
        'product_id' => 14,
        'product_url' => '/products/14',
        'charge_breakdown' => [
            'days_line' => 'Days: £125.00 × 3',
            'rental_charge_display' => '£750.00',
            'surcharge_display' => '£5.00',
        ],
        'availability_url' => '/availability?product=14',
        'starts_at' => '2026-11-01',
        'ends_at' => '2026-11-04',
        'charge_period_label' => 'Day',
        'has_duplicates' => false,
    ];
}

function quotationPlanningData(int $itemId): OpportunityItemPlanningData
{
    return new OpportunityItemPlanningData(
        opportunity_item_id: $itemId,
        path: '0001',
        parent_path: null,
        depth: 1,
        product_id: 14,
        product_name: 'Lighting package',
        store_id: 4,
        store_name: 'Main warehouse',
        planning_starts_at: '2026-11-01T08:00:00+00:00',
        planning_ends_at: '2026-11-04T18:00:00+00:00',
        requested_quantity: '2.00',
        reserved_quantity: '1.00',
        reservation_mode: 'explicit',
        reservation_state: 'partial',
        effective_claim_quantity: 1,
        reservation_gap: '1.00',
        available_quantity: 8,
        owned_available_quantity: 2,
        external_supply_quantity: 3,
        shortage_quantity: 0,
        has_shortage: false,
        allocated_quantity: 1,
        allocation_state: 'full',
    );
}
