<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Services;

use App\Data\Planning\OpportunityItemPlanningData;
use Illuminate\Support\Collection;

final class QuotationEditorTreeProjector
{
    /**
     * @var list<string>
     */
    private const ROW_FIELDS = [
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
    ];

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<int, OpportunityItemPlanningData>  $planningByItemId
     * @return array<int, array<string, mixed>>
     */
    public function project(array $rows, Collection $planningByItemId): array
    {
        return array_map(function (array $row) use ($planningByItemId): array {
            $projected = [];

            foreach (self::ROW_FIELDS as $field) {
                $projected[$field] = $row[$field] ?? null;
            }

            $planning = $planningByItemId->get((int) $projected['id']);
            $projected['planning'] = $planning instanceof OpportunityItemPlanningData
                ? $this->projectPlanning($planning)
                : null;

            return $projected;
        }, $rows);
    }

    /**
     * @return array{requested_quantity: string, reserved_quantity: string|null, reservation_mode: string, reservation_state: string, available_quantity: int, external_supply_quantity: int, shortage_quantity: int, has_shortage: bool, allocated_quantity: int|null}
     */
    private function projectPlanning(OpportunityItemPlanningData $planning): array
    {
        return [
            'requested_quantity' => $planning->requested_quantity,
            'reserved_quantity' => $planning->reserved_quantity,
            'reservation_mode' => $planning->reservation_mode,
            'reservation_state' => $planning->reservation_state,
            'available_quantity' => $planning->available_quantity,
            'external_supply_quantity' => $planning->external_supply_quantity,
            'shortage_quantity' => $planning->shortage_quantity,
            'has_shortage' => $planning->has_shortage,
            'allocated_quantity' => $planning->allocated_quantity,
        ];
    }
}
