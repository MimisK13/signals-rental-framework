<?php

use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\ShortageResolution;
use App\Models\ShortageResolutionItem;
use App\Models\User;
use App\Models\VirtualStockCostOverride;
use App\Models\VirtualStockIntake;
use App\Services\Shortages\CostApportionmentContext;
use App\Services\Shortages\CostApportionmentContextBuilder;
use App\Services\Shortages\Strategies\EvenSplitCostApportionmentStrategy;
use App\Services\Shortages\Strategies\ManualCostApportionmentStrategy;
use App\Services\Shortages\Strategies\PrimaryJobCostApportionmentStrategy;
use App\Services\Shortages\Strategies\ProportionalQuantityCostApportionmentStrategy;
use App\Services\Shortages\Strategies\ProportionalQuantityDurationCostApportionmentStrategy;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

/**
 * @param  list<Opportunity>  $opportunities
 * @param  array<int, int>  $quantitiesByOpportunity
 */
function strategyContext(
    VirtualStockIntake $intake,
    array $quantitiesByOpportunity,
    ?int $primaryOpportunityId = null,
    array $opportunities = [],
): CostApportionmentContext {
    return new CostApportionmentContext(
        intake: $intake,
        quantitiesByOpportunity: $quantitiesByOpportunity,
        opportunities: $opportunities,
        primaryOpportunityId: $primaryOpportunityId,
        totalCostMinor: (int) $intake->unit_cost * (int) $intake->quantity,
    );
}

function confirmedIntake(int $quantity = 3, int $unitCost = 1000): VirtualStockIntake
{
    return VirtualStockIntake::factory()->confirmed()->create([
        'quantity' => $quantity,
        'unit_cost' => $unitCost,
        'available_from' => Carbon::parse('2026-08-01T00:00:00Z'),
        'available_until' => Carbon::parse('2026-08-06T00:00:00Z'),
    ]);
}

it('primary job assigns full cost to the primary opportunity', function () {
    $intake = confirmedIntake(unitCost: 500);
    $context = strategyContext($intake, [10 => 2, 20 => 1], primaryOpportunityId: 20);

    $result = app(PrimaryJobCostApportionmentStrategy::class)->calculate($context);

    expect($result)->toBe([10 => 0, 20 => 1500]);
});

it('even split distributes remainder pennies to the lowest opportunity id first', function () {
    $intake = confirmedIntake(quantity: 1, unitCost: 100);
    $context = strategyContext($intake, [30 => 1, 10 => 1, 20 => 1]);

    $result = app(EvenSplitCostApportionmentStrategy::class)->calculate($context);

    expect(array_sum($result))->toBe(100)
        ->and($result[10])->toBe(34)
        ->and($result[20])->toBe(33)
        ->and($result[30])->toBe(33);
});

it('proportional quantity splits by pivot quantities', function () {
    $intake = confirmedIntake(quantity: 7, unitCost: 100);
    $context = strategyContext($intake, [1 => 4, 2 => 3]);

    $result = app(ProportionalQuantityCostApportionmentStrategy::class)->calculate($context);

    expect(array_sum($result))->toBe(700)
        ->and($result[1])->toBe(400)
        ->and($result[2])->toBe(300);
});

it('proportional quantity duration uses unit-days and leaves zero-overlap opportunities at zero', function () {
    $intake = confirmedIntake(quantity: 1, unitCost: 1800);

    $overlapOpp = Opportunity::factory()->create([
        'starts_at' => Carbon::parse('2026-08-02T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-08-04T17:00:00Z'),
    ]);
    $zeroOpp = Opportunity::factory()->create([
        'starts_at' => Carbon::parse('2026-08-10T00:00:00Z'),
        'ends_at' => Carbon::parse('2026-08-12T00:00:00Z'),
    ]);

    $context = strategyContext(
        $intake,
        [$overlapOpp->id => 4, $zeroOpp->id => 3],
        opportunities: [
            $overlapOpp->id => $overlapOpp,
            $zeroOpp->id => $zeroOpp,
        ],
    );

    $result = app(ProportionalQuantityDurationCostApportionmentStrategy::class)->calculate($context);

    expect($result[$zeroOpp->id])->toBe(0)
        ->and($result[$overlapOpp->id])->toBe(1800)
        ->and(array_sum($result))->toBe(1800);
});

it('manual strategy uses overrides and zeroes missing opportunities', function () {
    $intake = confirmedIntake(quantity: 2, unitCost: 9999);
    $withOverride = Opportunity::factory()->create();
    $missing = Opportunity::factory()->create();

    VirtualStockCostOverride::factory()->create([
        'virtual_stock_intake_id' => $intake->id,
        'opportunity_id' => $withOverride->id,
        'unit_cost_override' => 250,
    ]);

    $context = strategyContext($intake, [
        $withOverride->id => 2,
        $missing->id => 1,
    ]);

    $result = app(ManualCostApportionmentStrategy::class)->calculate($context);

    expect($result[$withOverride->id])->toBe(500)
        ->and($result[$missing->id])->toBe(0);
});

it('context builder aggregates pivot quantities and primary opportunity metadata', function () {
    $intake = confirmedIntake();
    $resolution = ShortageResolution::factory()->create([
        'virtual_stock_intake_id' => $intake->id,
        'metadata' => ['opportunity_id' => 77],
    ]);

    $oppA = Opportunity::factory()->create();
    $oppB = Opportunity::factory()->create();
    $itemA = OpportunityItem::factory()->for($oppA)->create();
    $itemB = OpportunityItem::factory()->for($oppB)->create();

    ShortageResolutionItem::factory()->create([
        'shortage_resolution_id' => $resolution->id,
        'opportunity_item_id' => $itemA->id,
        'quantity_allocated' => 2,
    ]);
    ShortageResolutionItem::factory()->create([
        'shortage_resolution_id' => $resolution->id,
        'opportunity_item_id' => $itemB->id,
        'quantity_allocated' => 1,
    ]);

    $context = app(CostApportionmentContextBuilder::class)->build($intake);

    expect($context->primaryOpportunityId)->toBe(77)
        ->and($context->quantitiesByOpportunity)->toBe([
            $oppA->id => 2,
            $oppB->id => 1,
        ]);
});
