<?php

use App\Actions\Opportunities\ChangeItemQuantity;
use App\Actions\Opportunities\ExpandOpportunityCombination;
use App\Actions\Opportunities\RemoveOpportunityItem;
use App\Data\Opportunities\ChangeItemQuantityData;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\SerialisedComponent;
use App\Models\Store;
use App\Models\User;
use App\Services\Planning\OpportunityItemPlanningReader;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

function expandableOpportunity(): Opportunity
{
    $store = Store::factory()->create();

    return Opportunity::factory()->create([
        'store_id' => $store->id,
        'starts_at' => '2026-07-10 08:00:00',
        'ends_at' => '2026-07-13 18:00:00',
        'planning_starts_at' => '2026-07-10 08:00:00',
        'planning_ends_at' => '2026-07-13 18:00:00',
    ]);
}

it('expands a product composition into a quantity-scaled group and ordinary children', function (): void {
    $opportunity = expandableOpportunity();
    $package = Product::factory()->create(['name' => 'Small PA Package', 'is_kit' => true]);
    $speaker = Product::factory()->create(['name' => 'Speaker']);
    $amp = Product::factory()->create(['name' => 'Amplifier']);
    $cable = Product::factory()->create(['name' => 'Cable']);

    SerialisedComponent::factory()->pool()->create(['product_id' => $package->id, 'component_product_id' => $speaker->id, 'quantity' => '2', 'sort_order' => 1]);
    SerialisedComponent::factory()->pool()->create(['product_id' => $package->id, 'component_product_id' => $amp->id, 'quantity' => '1', 'sort_order' => 2]);
    SerialisedComponent::factory()->pool()->create(['product_id' => $package->id, 'component_product_id' => $cable->id, 'quantity' => '4', 'sort_order' => 3]);

    (new ExpandOpportunityCombination)($opportunity, $package, '2');

    $items = $opportunity->fresh(['items'])->items->sortBy('path')->values();
    $planning = app(OpportunityItemPlanningReader::class)->forOpportunity($opportunity->fresh());

    expect($items)->toHaveCount(4)
        ->and($items[0]->item_type->value)->toBe('group')
        ->and($items[0]->name)->toBe('Small PA Package')
        ->and($items[0]->quantity)->toBe('2.00')
        ->and($items->pluck('quantity', 'itemable_id')->get($speaker->id))->toBe('4.00')
        ->and($items->pluck('quantity', 'itemable_id')->get($amp->id))->toBe('2.00')
        ->and($items->pluck('quantity', 'itemable_id')->get($cable->id))->toBe('8.00')
        ->and($items->skip(1)->every(fn (OpportunityItem $item): bool => $item->item_type->value === 'product'))->toBeTrue()
        ->and($items->skip(1)->every(fn (OpportunityItem $item): bool => $item->reserved_quantity === null))->toBeTrue()
        ->and($planning->count())->toBe(3)
        ->and($planning->first()->path)->toBe('00010001')
        ->and($planning->first()->parent_path)->toBe('0001')
        ->and($planning->first()->depth)->toBe(2);
});

it('freezes the expanded children and allows child customization without changing the template', function (): void {
    $opportunity = expandableOpportunity();
    $newOpportunity = expandableOpportunity();
    $package = Product::factory()->create(['name' => 'Package', 'is_kit' => true]);
    $speaker = Product::factory()->create(['name' => 'Speaker']);

    SerialisedComponent::factory()->pool()->create([
        'product_id' => $package->id,
        'component_product_id' => $speaker->id,
        'quantity' => '2',
    ]);

    (new ExpandOpportunityCombination)($opportunity, $package, '1');

    $component = SerialisedComponent::query()->whereKey(
        SerialisedComponent::query()->where('product_id', $package->id)->value('id'),
    )->firstOrFail();
    $component->update(['quantity' => '4']);

    (new ExpandOpportunityCombination)($newOpportunity, $package, '1');

    $oldChild = $opportunity->fresh(['items'])->items->firstWhere('itemable_id', $speaker->id);
    $newChild = $newOpportunity->fresh(['items'])->items->firstWhere('itemable_id', $speaker->id);

    expect($oldChild?->quantity)->toBe('2.00')
        ->and($newChild?->quantity)->toBe('4.00');

    (new ChangeItemQuantity)($oldChild, ChangeItemQuantityData::from(['quantity' => '3']));

    expect($oldChild->fresh()->quantity)->toBe('3.00')
        ->and($component->fresh()->quantity)->toBe('4.0000');
});

it('does not create a demand for the structural parent', function (): void {
    $opportunity = expandableOpportunity();
    $package = Product::factory()->create(['name' => 'Package', 'is_kit' => true]);
    $speaker = Product::factory()->create(['name' => 'Speaker']);

    SerialisedComponent::factory()->pool()->create([
        'product_id' => $package->id,
        'component_product_id' => $speaker->id,
        'quantity' => '2',
    ]);

    (new ExpandOpportunityCombination)($opportunity, $package, '2');

    $items = $opportunity->fresh(['items'])->items;

    $parent = $items->first();
    $child = $items->where('itemable_id', $speaker->id)->first();

    expect($parent->item_type->value)->toBe('group')
        ->and(Demand::query()->where('source_type', 'opportunity_item')->where('source_id', $parent->id)->count())->toBe(0)
        ->and(Demand::query()->where('source_type', 'opportunity_item')->where('source_id', $child->id)->count())->toBe(1)
        ->and(Demand::query()->where('source_type', 'opportunity_item')->where('source_id', $child->id)->value('quantity'))->toBe(4);
});

it('replays the frozen tree without reading the current product template', function (): void {
    $opportunity = expandableOpportunity();
    $package = Product::factory()->create(['name' => 'Package', 'is_kit' => true]);
    $speaker = Product::factory()->create(['name' => 'Speaker']);

    SerialisedComponent::factory()->pool()->create([
        'product_id' => $package->id,
        'component_product_id' => $speaker->id,
        'quantity' => '2',
    ]);

    (new ExpandOpportunityCombination)($opportunity, $package, '1');
    $childId = $opportunity->fresh(['items'])->items->firstWhere('itemable_id', $speaker->id)->id;

    SerialisedComponent::query()
        ->where('product_id', $package->id)
        ->update(['quantity' => '4']);

    Verbs::replay();

    expect(OpportunityItem::query()->whereKey($childId)->value('quantity'))->toBe('2.00');
});

it('removes an expanded group and its planned children through the existing tree semantics', function (): void {
    $opportunity = expandableOpportunity();
    $package = Product::factory()->create(['name' => 'Package', 'is_kit' => true]);
    $speaker = Product::factory()->create(['name' => 'Speaker']);

    SerialisedComponent::factory()->pool()->create([
        'product_id' => $package->id,
        'component_product_id' => $speaker->id,
        'quantity' => '1',
    ]);

    (new ExpandOpportunityCombination)($opportunity, $package);
    $parent = $opportunity->fresh(['items'])->items->firstWhere('item_type', 'group');

    (new RemoveOpportunityItem)($parent);

    expect($opportunity->fresh(['items'])->items)->toBeEmpty();
});
