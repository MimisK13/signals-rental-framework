<?php

use App\Contracts\Opportunities\OpportunityLineItemsEditorContract;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityItemType;
use App\Enums\OpportunityState;
use App\Livewire\Experimental\QuotationEditor;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('renders the experimental quotation editor from the current production contract', function (): void {
    $opportunity = Opportunity::factory()->create([
        'state' => OpportunityState::Quotation,
    ]);

    $component = Livewire::test(QuotationEditor::class, [
        'opportunity' => $opportunity,
    ]);

    expect($component->instance())->toBeInstanceOf(OpportunityLineItemsEditorContract::class)
        ->and(method_exists($component->instance(), 'removeItem'))->toBeTrue()
        ->and(method_exists($component->instance(), 'deleteSection'))->toBeTrue();
});

it('keeps the catalogue out of persistent Livewire state', function (): void {
    $opportunity = Opportunity::factory()->create([
        'state' => OpportunityState::Quotation,
    ]);

    $instance = Livewire::test(QuotationEditor::class, [
        'opportunity' => $opportunity,
    ])->instance();

    expect(property_exists($instance, 'catalogue'))->toBeFalse()
        ->and(method_exists($instance, 'catalogueIndex'))->toBeTrue();
});

it('acknowledges an unchanged editor revision without rebuilding the tree', function (): void {
    $opportunity = Opportunity::factory()->create([
        'state' => OpportunityState::Quotation,
    ]);

    $instance = Livewire::test(QuotationEditor::class, [
        'opportunity' => $opportunity,
    ])->instance();
    $instance->addInlineTextLineSnapshot();
    $revision = $instance->treeRevision();
    $payload = $instance->pullTree($revision, [], [], false);

    expect($payload)
        ->toMatchArray([
            'changed' => false,
            'stale' => false,
            'revision' => (string) $revision,
        ])
        ->not->toHaveKey('tree');

    expect($instance->pullTree($revision, [], [], true))
        ->toMatchArray(['changed' => true])
        ->toHaveKey('tree');
});

it('keeps the experimental and production editor ownership boundaries isolated', function (): void {
    $experimentalEdit = file_get_contents(resource_path('views/livewire/experimental/quotations/edit.blade.php'));
    $productionShow = file_get_contents(resource_path('views/livewire/opportunities/show.blade.php'));

    expect($experimentalEdit)
        ->toContain('<livewire:experimental.quotation-editor');
    expect(str_contains($experimentalEdit, '<livewire:opportunities.line-items'))
        ->toBeFalse();

    expect($productionShow)
        ->toContain('<livewire:opportunities.line-items');
    expect(str_contains($productionShow, '<livewire:experimental.quotation-editor'))
        ->toBeFalse();
});

it('adds a product without lazy loading the item opportunity during shortage probing', function (): void {
    $opportunity = Opportunity::factory()->create([
        'state' => OpportunityState::Quotation,
    ]);
    $product = Product::factory()->rental()->bulk()->create();

    $instance = Livewire::test(QuotationEditor::class, [
        'opportunity' => $opportunity,
    ])->instance();

    $instance->addProduct($product->id, 1.0, '');

    expect(OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('itemable_id', $product->id)
        ->where('itemable_type', Product::class)
        ->where('item_type', OpportunityItemType::Product->value)
        ->where('transaction_type', LineItemTransactionType::Rental->value)
        ->exists())->toBeTrue();
});

it('returns only the compact row and planning contract used by the experimental editor', function (): void {
    $store = Store::factory()->create();
    $opportunity = Opportunity::factory()->create([
        'state' => OpportunityState::Quotation,
        'store_id' => $store->id,
    ]);
    $product = Product::factory()->rental()->bulk()->create();
    $instance = Livewire::test(QuotationEditor::class, [
        'opportunity' => $opportunity,
    ])->instance();

    $instance->addProduct($product->id);
    $row = collect($instance->tree())->firstWhere('product_id', $product->id);

    expect($row)
        ->not->toBeNull()
        ->toHaveKeys([
            'id',
            'path',
            'unit_price_raw',
            'charge_period_label',
            'charge_breakdown',
            'availability_url',
            'planning',
        ])
        ->not->toHaveKeys([
            'parent_path',
            'revenue_group_id',
            'quantity_raw',
            'unit_price_display',
            'charge_total_display',
            'surcharge_total',
            'surcharge_total_display',
            'availability_status',
            'is_collapsed',
            'has_children',
        ]);

    expect(array_keys($row['planning']))->toBe([
        'requested_quantity',
        'reserved_quantity',
        'reservation_mode',
        'reservation_state',
        'available_quantity',
        'external_supply_quantity',
        'shortage_quantity',
        'has_shortage',
        'allocated_quantity',
    ]);
});

it('applies queued field changes through one server batch', function (): void {
    $opportunity = Opportunity::factory()->create([
        'state' => OpportunityState::Quotation,
    ]);
    $product = Product::factory()->rental()->bulk()->create();
    $instance = Livewire::test(QuotationEditor::class, [
        'opportunity' => $opportunity,
    ])->instance();

    $instance->addProduct($product->id);
    $item = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('itemable_id', $product->id)
        ->firstOrFail();

    $snapshot = $instance->applyFieldMutations([
        ['id' => $item->id, 'field' => 'quantity', 'value' => '2'],
        ['id' => $item->id, 'field' => 'unit_price', 'value' => '12.50'],
    ]);
    $item->refresh();

    expect((float) $item->quantity)->toBe(2.0)
        ->and($item->unit_price)->toBe(1250)
        ->and($snapshot)->toHaveKeys(['revision', 'charge_total', 'deal_total', 'has_deal_price']);
});

it('adds an empty text item through the lightweight canonical action', function (): void {
    $opportunity = Opportunity::factory()->create([
        'state' => OpportunityState::Quotation,
    ]);

    $instance = Livewire::test(QuotationEditor::class, [
        'opportunity' => $opportunity,
    ])->instance();

    $instance->addInlineTextLineSnapshot();

    expect(OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('item_type', OpportunityItemType::Text->value)
        ->where('name', '')
        ->exists())->toBeTrue();
});

it('uses the production pointer drag engine only for the experimental editor grid', function (): void {
    $experimentalGrid = file_get_contents(resource_path('views/livewire/experimental/quotation-editor/grid.blade.php'));
    $experimentalToolbar = file_get_contents(resource_path('views/livewire/experimental/quotation-editor/toolbar.blade.php'));
    $experimentalDrag = file_get_contents(resource_path('js/experimental/quotation-editor/tree-drag.js'));
    $productionEditor = file_get_contents(resource_path('views/livewire/opportunities/line-items.blade.php'));

    expect($experimentalGrid)
        ->toContain('@pointerdown="onHandleDown($event, row.id)"')
        ->toContain('SECTION')
        ->toContain('<x-dense::icons.folder-plus size="4" />')
        ->toContain('<x-dense::icons.folder-minus size="4" />')
        ->toContain('editable && row.depth <= 1')
        ->toContain('editable && row.depth > 1 && row.item_type !== \'group\'')
        ->toContain('row.product_id && row.planning')
        ->not->toContain('>LEGACY</span>')
        ->toContain('viewBox="0 0 16 16"')
        ->toContain('<table class="s-table s-table-compact w-full select-none [&_thead_th]:!py-[10px] [&_thead_th]:!text-[9px] [&_thead_th]:!font-semibold">')
        ->toContain('<x-dense::table-card')
        ->toContain('hasSectionChildren')
        ->toContain('s-btn s-btn-ghost s-btn-xs lf-add-text-btn')
        ->not->toContain('<x-dense::table compact')
        ->not->toContain('x-sort')
        ->not->toContain('wire:sort')
        ->not->toContain('x-sort:handle');

    expect($experimentalDrag)
        ->toContain('createTreeDragController')
        ->toContain('resolveDropTarget')
        ->toContain('pointermove')
        ->toContain('pointerup');

    expect($experimentalToolbar)
        ->not->toContain('lf-toolbar-sticky');

    expect($productionEditor)
        ->not->toContain('x-sort="handleSort($item, $position)"')
        ->not->toContain('x-sort:item="row.id"');
});
