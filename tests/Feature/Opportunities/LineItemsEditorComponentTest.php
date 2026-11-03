<?php

use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\SerialisedComponent;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityItem;
use App\Domain\Projects\Opportunities\Actions\CreateOpportunity;
use App\Domain\Projects\Opportunities\Data\AddOpportunityItemData;
use App\Domain\Projects\Opportunities\Data\CreateOpportunityData;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Presentation\Livewire\LineItems;
use App\Domain\Stock\Models\StockLevel;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

it('exposes tree and revision for the frozen seed island', function () {
    Auth::login($this->owner);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Seed contract',
        'store_id' => $this->store->id,
    ]));

    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    $this->actingAs($this->owner);

    Livewire::test(LineItems::class, ['opportunity' => $opportunity])
        ->assertOk()
        ->assertDispatched('opportunity-shortage-summary-updated', opportunityId: $opportunity->id, unresolvedCount: 0)
        ->assertSeeHtml('data-lf-seed')
        ->assertSeeHtml('window.__lfSeed')
        ->assertSeeHtml('window.__lfEditorConfig')
        ->assertSeeHtml('wire:ignore')
        ->assertSeeHtml('x-data="window.signals.lineItemsEditor({ oppId: '.$opportunity->id.' })"')
        ->assertSeeHtml('window.__lfEditorConfig')
        ->assertSee('Quick add');

    $component = Livewire::test(LineItems::class, ['opportunity' => $opportunity]);
    $instance = lineItemsEditorInstance($component);

    expect($instance->treeRevision())->toBeInt();

    $server = $instance->serverTree();

    expect($server)->toHaveKeys(['tree', 'revision']);
});

it('keeps a stable x-data attribute after inline add so Alpine is not re-initialised', function () {
    Auth::login($this->owner);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Stable x-data',
        'store_id' => $this->store->id,
    ]));

    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    $this->actingAs($this->owner);

    $stableXData = 'x-data="window.signals.lineItemsEditor({ oppId: '.$opportunity->id.' })"';

    Livewire::test(LineItems::class, ['opportunity' => $opportunity])
        ->assertSeeHtml($stableXData)
        ->call('addInlineTextLine')
        ->assertHasNoErrors()
        ->assertSeeHtml($stableXData);
});

it('renders read-only for view-only users', function () {
    Auth::login($this->owner);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Read only',
        'store_id' => $this->store->id,
    ]));

    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('opportunities.access', 'opportunities.view');
    $this->actingAs($viewer);

    Livewire::test(LineItems::class, ['opportunity' => $opportunity])
        ->assertOk()
        ->assertSee('This opportunity has no line items yet.')
        ->assertDontSee('Quick add');
});

it('adds a combination through the quotation editor and preserves the item tree', function () {
    Auth::login($this->owner);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Combination quotation',
        'store_id' => $this->store->id,
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    $package = Product::factory()->kit()->create(['name' => 'Small PA Package']);
    $speaker = Product::factory()->create(['name' => 'Speaker']);
    $amp = Product::factory()->create(['name' => 'Amplifier']);

    SerialisedComponent::factory()->pool()->create([
        'product_id' => $package->id,
        'component_product_id' => $speaker->id,
        'quantity' => '2',
        'sort_order' => 1,
    ]);
    SerialisedComponent::factory()->pool()->create([
        'product_id' => $package->id,
        'component_product_id' => $amp->id,
        'quantity' => '1',
        'sort_order' => 2,
    ]);

    Livewire::test(LineItems::class, ['opportunity' => $opportunity])
        ->call('addCombination', $package->id, 2)
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success', message: 'Combination added');

    $items = $opportunity->fresh(['items'])->items->sortBy('path')->values();

    $packageGroup = $items->firstWhere('name', 'Small PA Package');

    expect($items)->toHaveCount(3)
        ->and($packageGroup?->item_type->value)->toBe('group')
        ->and($packageGroup?->quantity)->toBe('2.00')
        ->and($items->firstWhere('itemable_id', $speaker->id)?->quantity)->toBe('4.00')
        ->and($items->firstWhere('itemable_id', $amp->id)?->quantity)->toBe('2.00');
});

it('exposes canonical planning data on physical rows without adding it to groups', function () {
    Auth::login($this->owner);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Planning quotation',
        'store_id' => $this->store->id,
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    $product = Product::factory()->bulk()->create(['name' => 'Planning Cable']);

    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 20,
    ]);
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '4',
    ]));

    $rows = lineItemsEditorInstance(
        Livewire::test(LineItems::class, ['opportunity' => $opportunity->fresh(['items'])])
    )->serverTree()['tree'];
    $row = collect($rows)->firstWhere('product_id', $product->id);

    expect($row['planning']['requested_quantity'])->toBe('4.00')
        ->and($row['planning']['available_quantity'])->toBe(20)
        ->and($row['planning']['reservation_mode'])->toBe('legacy')
        ->and(collect($rows)->where('item_type', 'group')->every(
            fn (array $group): bool => $group['planning'] === null,
        ))->toBeTrue();
});
