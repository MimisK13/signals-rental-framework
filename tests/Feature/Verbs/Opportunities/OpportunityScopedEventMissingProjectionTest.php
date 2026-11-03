<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityCost;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityItem;
use App\Domain\Projects\Opportunities\Actions\CreateOpportunity;
use App\Domain\Projects\Opportunities\Data\AddOpportunityCostData;
use App\Domain\Projects\Opportunities\Data\AddOpportunityItemData;
use App\Domain\Projects\Opportunities\Data\CreateOpportunityData;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityCost;
use App\Domain\Projects\Opportunities\Verbs\Events\CostRemoved;
use App\Domain\Projects\Opportunities\Verbs\Events\CostUpdated;
use App\Domain\Projects\Opportunities\Verbs\Events\DealPriceCleared;
use App\Domain\Projects\Opportunities\Verbs\Events\DealPriceSet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Thunk\Verbs\Facades\Verbs;

/**
 * Covers the defensive `if (… === null) { return; }` guard in the handle() of the
 * cost events (keyed on the `opportunity_costs` projection row) and the deal-price
 * events (keyed on the `opportunities` projection row). The guard fires when the
 * relevant projection row was hard-deleted out from under a later event in the
 * same Verbs stream.
 *
 * Mechanism (per {@see ItemEventMissingProjectionTest}): build the entity, delete
 * ONLY the projection row the handle looks up (the Verbs state survives so
 * validate() still passes), fire the event once, assert the no-op.
 */
beforeEach(function () {
    Queue::fake();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
});

function scopedGuardOpportunity(): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Scoped guard']));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

it('cost handle() guards a missing projection row', function (callable $fire) {
    $opportunity = scopedGuardOpportunity();
    (new AddOpportunityCost)($opportunity, AddOpportunityCostData::from(['description' => 'Fuel', 'amount' => 2500]));
    $cost = $opportunity->costs()->firstOrFail();

    // Unwrap the lazy dataset closure once to get the real fire callable.
    $fire = $fire();

    $stateId = $cost->state_id;
    OpportunityCost::query()->whereKey($cost->id)->delete();

    DB::transaction(function () use ($stateId, $fire) {
        $fire($stateId);
        Verbs::commit();
    });

    expect(OpportunityCost::query()->whereKey($cost->id)->exists())->toBeFalse();
})->with([
    'removed' => [fn () => fn (int $id) => CostRemoved::fire(opportunity_cost_id: $id)],
    'updated' => [fn () => fn (int $id) => CostUpdated::fire(
        opportunity_cost_id: $id, description: 'Diesel', amount: 3000,
    )],
]);

it('deal-price handle() guards a missing opportunity projection row', function (callable $fire) {
    $opportunity = scopedGuardOpportunity();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Mixer', 'quantity' => '1', 'unit_price' => 4000,
    ]));

    // Unwrap the lazy dataset closure once to get the real fire callable.
    $fire = $fire();

    $stateId = $opportunity->state_id;
    $opportunityId = $opportunity->id;
    // DealPrice validate() reads the Verbs state (isClosed), not the row, so the
    // guard is reachable by deleting the projection row alone.
    Opportunity::query()->whereKey($opportunityId)->forceDelete();

    DB::transaction(function () use ($stateId, $fire) {
        $fire($stateId);
        Verbs::commit();
    });

    expect(Opportunity::withTrashed()->whereKey($opportunityId)->exists())->toBeFalse();
})->with([
    'set' => [fn () => fn (int $id) => DealPriceSet::fire(opportunity_id: $id, deal_total: 7500)],
    'cleared' => [fn () => fn (int $id) => DealPriceCleared::fire(opportunity_id: $id)],
]);
