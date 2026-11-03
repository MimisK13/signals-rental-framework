<?php

use App\Domain\Availability\Models\Demand;
use App\Domain\Products\Models\Product;
use App\Domain\Projects\Opportunities\Actions\ActivateVersion;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityItem;
use App\Domain\Projects\Opportunities\Actions\ChangeOpportunityStatus;
use App\Domain\Projects\Opportunities\Actions\ConvertToQuotation;
use App\Domain\Projects\Opportunities\Actions\CreateOpportunity;
use App\Domain\Projects\Opportunities\Actions\CreateVersion;
use App\Domain\Projects\Opportunities\Data\AddOpportunityItemData;
use App\Domain\Projects\Opportunities\Data\CreateOpportunityData;
use App\Domain\Projects\Opportunities\Data\CreateVersionData;
use App\Domain\Projects\Opportunities\Enums\OpportunityStatus;
use App\Domain\Projects\Opportunities\Enums\VersionType;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityVersion;
use App\Domain\Stock\Models\StockLevel;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Tests\Concerns\UsesPostgres;

/*
|--------------------------------------------------------------------------
| PostgreSQL quote-version demand-swap lane
|--------------------------------------------------------------------------
|
| Proves the demand SWAP on version activation (§8.9) holds on PostgreSQL,
| where the serialised-asset exclusion constraint (excl_demands_asset_period)
| is enforced. The previously-active version's demands MUST be released before
| the new active version's demands are synced, otherwise two active demands for
| the same asset/window would violate the constraint (23P01). Runs against the
| dedicated pgsql_testing connection; SKIPs when Postgres is unreachable.
|
|   php artisan test --compact --group=pgsql
|
*/

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    $this->store = Store::factory()->create();
});

it('swaps serialised-asset demands between versions without violating the exclusion constraint', function () {
    // A serialised product with one physical asset both versions claim against —
    // the same asset over the same window can only hold ONE active demand at a
    // time, so a clean release-before-sync is mandatory.
    $product = Product::factory()->rental()->serialised()->create();
    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
    ]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'PG version swap',
        'store_id' => $this->store->id,
        'starts_at' => '2026-09-01T09:00:00Z',
        'ends_at' => '2026-09-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name, 'itemable_id' => $product->id, 'itemable_type' => Product::class, 'quantity' => '1',
    ]));
    (new ConvertToQuotation)($opportunity->refresh());

    $v1 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));
    // Reserve so demands become active.
    (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::QuotationReserved);

    $v1Items = OpportunityVersion::query()->whereKey($v1->id)->firstOrFail()->items->pluck('id');
    expect(Demand::query()->whereIn('source_id', $v1Items)->where('is_active', true)->count())->toBeGreaterThan(0);

    // Create an alternative claiming the SAME product/asset/window; it becomes
    // active. The swap must release v1's demands first — if it did not, syncing
    // v2's asset demand against the same window would throw 23P01.
    $v2 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from(['version_type' => VersionType::Alternative->value]));
    $opportunity->refresh();

    expect(Demand::query()->whereIn('source_id', $v1Items)->where('is_active', true)->count())->toBe(0);
    $v2Items = OpportunityVersion::query()->whereKey($v2->id)->firstOrFail()->items->pluck('id');
    expect(Demand::query()->whereIn('source_id', $v2Items)->where('is_active', true)->count())->toBeGreaterThan(0);

    // Switch back to v1: the swap releases v2's, re-activates v1's — again no
    // overlapping active demand for the asset.
    (new ActivateVersion)(OpportunityVersion::query()->whereKey($v1->id)->firstOrFail());

    expect(Demand::query()->whereIn('source_id', $v2Items)->where('is_active', true)->count())->toBe(0)
        ->and(Demand::query()->whereIn('source_id', $v1Items)->where('is_active', true)->count())->toBeGreaterThan(0);
});
