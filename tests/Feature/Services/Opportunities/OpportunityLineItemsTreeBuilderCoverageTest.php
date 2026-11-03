<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Products\Models\Product;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityGroup;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityItem;
use App\Domain\Projects\Opportunities\Actions\CreateOpportunity;
use App\Domain\Projects\Opportunities\Data\AddOpportunityGroupData;
use App\Domain\Projects\Opportunities\Data\AddOpportunityItemData;
use App\Domain\Projects\Opportunities\Data\CreateOpportunityData;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Services\OpportunityEditorTreeService;
use App\Domain\Projects\Opportunities\Services\OpportunityLineItemsTreeBuilder;
use App\Domain\Stores\Models\Store;
use App\Support\Formatter;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

function treeBuilderCovOpportunity(): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Tree cov',
        'store_id' => test()->store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-04T09:00:00Z',
    ]));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

it('labels an auto-group destination with the Group prefix', function () {
    $opportunity = treeBuilderCovOpportunity();

    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from([
        'name' => 'Lighting',
        'custom_fields' => [OpportunityEditorTreeService::AUTO_GROUP_KEY_FIELD => 'auto:lighting'],
    ]));

    $destinations = app(OpportunityLineItemsTreeBuilder::class)->destinations($opportunity->fresh());

    expect(collect($destinations)->pluck('label')->all())->toContain('Group · Lighting');
});

it('labels unknown group kinds without a prefix', function () {
    $opportunity = treeBuilderCovOpportunity();

    $treeService = Mockery::mock(OpportunityEditorTreeService::class);
    $treeService->shouldReceive('buildDisplayGroups')->once()->andReturn([
        ['key' => 'custom:1', 'label' => 'Custom node', 'kind' => 'section'],
    ]);

    $builder = new OpportunityLineItemsTreeBuilder(
        app(Formatter::class),
        app(AvailabilityService::class),
        $treeService,
    );

    $labels = collect($builder->destinations($opportunity))->pluck('label');

    expect($labels)->toContain('Custom node')
        ->and($labels->contains(fn (string $label): bool => str_starts_with($label, 'Section · ')))->toBeFalse();
});

it('resolves a product id from a legacy lowercase namespaced itemable type', function () {
    $opportunity = treeBuilderCovOpportunity();
    $product = Product::factory()->create();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Legacy link',
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'unit_price' => 1000,
    ]));

    // Rewrite the stored type to a lowercase namespaced form so isProductBacked()
    // is false but the catalogue resolver's str_ends_with('\product') path matches.
    $opportunity->allItems()->where('name', 'Legacy link')->firstOrFail()
        ->forceFill(['itemable_type' => 'app\\models\\product'])->saveQuietly();

    $tree = app(OpportunityLineItemsTreeBuilder::class)->tree($opportunity->fresh());
    $row = collect($tree)->firstWhere('name', 'Legacy link');

    expect($row['product_id'])->toBe($product->id);
});

it('returns no product id for a non-product itemable type', function () {
    $opportunity = treeBuilderCovOpportunity();
    $product = Product::factory()->create();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Service link',
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'unit_price' => 1000,
    ]));

    // A genuinely non-product itemable type -> resolver falls through to null.
    $opportunity->allItems()->where('name', 'Service link')->firstOrFail()
        ->forceFill(['itemable_type' => 'App\\Models\\Service'])->saveQuietly();

    $tree = app(OpportunityLineItemsTreeBuilder::class)->tree($opportunity->fresh());
    $row = collect($tree)->firstWhere('name', 'Service link');

    expect($row['product_id'])->toBeNull();
});
