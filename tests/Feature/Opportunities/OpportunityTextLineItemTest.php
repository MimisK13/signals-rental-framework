<?php

use App\Domain\Projects\Opportunities\Actions\AddOpportunityItem;
use App\Domain\Projects\Opportunities\Actions\CreateOpportunity;
use App\Domain\Projects\Opportunities\Data\AddOpportunityItemData;
use App\Domain\Projects\Opportunities\Data\CreateOpportunityData;
use App\Domain\Projects\Opportunities\Enums\OpportunityItemType;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Presentation\Livewire\LineItems;
use App\Domain\Projects\Opportunities\Services\OpportunityLineItemsTreeBuilder;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

function textLineOpportunity(): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Text line test',
        'store_id' => test()->store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-03T09:00:00Z',
    ]));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

it('adds a chargeable free text item via AddOpportunityItem with manual pricing and no demand', function () {
    $opportunity = textLineOpportunity();

    (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
        'name' => 'Delivery',
        'item_type' => OpportunityItemType::Text->value,
        'quantity' => '1',
        'unit_price' => 5000,
    ]));

    $textLine = $opportunity->fresh(['items'])->items
        ->firstWhere('item_type', OpportunityItemType::Text);

    expect($textLine)->not->toBeNull()
        ->and($textLine->name)->toBe('Delivery')
        ->and((int) $textLine->total)->toBe(10000)
        ->and($textLine->itemable_id)->toBeNull()
        ->and((int) $opportunity->fresh()->charge_total)->toBe(10000);
});

it('exposes free text items in the editor tree with pricing cells and no availability metadata', function () {
    $opportunity = textLineOpportunity();

    (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
        'name' => 'Setup note',
        'item_type' => OpportunityItemType::Text->value,
        'quantity' => '1',
        'unit_price' => 2500,
    ]));

    $tree = app(OpportunityLineItemsTreeBuilder::class)->tree($opportunity->fresh(['items']));

    $row = collect($tree)->first(fn (array $node): bool => ($node['item_type'] ?? null) === 'text');

    expect($row)->not->toBeNull()
        ->and($row['name'])->toBe('Setup note')
        ->and($row['type_label'])->toBe('Free text item')
        ->and($row['status_label'])->toBeNull()
        ->and($row['charge_breakdown'])->not->toBeNull()
        ->and($row['charge_total'])->toBeGreaterThan(0);
});

it('adds a free text item through the inline editor action', function () {
    $opportunity = textLineOpportunity();

    Livewire::test(LineItems::class, ['opportunity' => $opportunity])
        ->call('addInlineTextLine')
        ->assertHasNoErrors();

    $line = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Text)
        ->first();

    expect($line)->not->toBeNull()
        ->and($line->name)->toBe('')
        ->and((float) $line->quantity)->toBe(1.0)
        ->and((int) $line->total)->toBe(0);
});

it('still validates modal addTextLine when a name is required', function () {
    $opportunity = textLineOpportunity();

    Livewire::test(LineItems::class, ['opportunity' => $opportunity])
        ->set('newTextLineName', '   ')
        ->call('addTextLine')
        ->assertHasErrors(['newTextLineName']);

    expect(OpportunityItem::query()->where('opportunity_id', $opportunity->id)->count())->toBe(0);
});

it('applies discount to a priced free text item', function () {
    $opportunity = textLineOpportunity();

    (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
        'name' => 'Rigging labour',
        'item_type' => OpportunityItemType::Text->value,
        'quantity' => '1',
        'unit_price' => 10000,
        'discount_percent' => '10',
    ]));

    $textLine = $opportunity->fresh(['items'])->items
        ->firstWhere('item_type', OpportunityItemType::Text);

    expect((int) $textLine->total)->toBe(18000)
        ->and((int) $opportunity->fresh()->charge_total)->toBe(18000);
});
