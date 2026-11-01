<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\LineItemTransactionType;
use App\Livewire\Experimental\QuotationEditor;
use App\Models\ActionLog;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RateDefinition;
use App\Models\Store;
use App\Models\User;
use App\Services\Notifications\EventNotificationMapRegistry;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Lifecycle\StateManager;
use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\Models\VerbStateEvent;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

function itemPricingBatchEditor(Opportunity $opportunity): QuotationEditor
{
    $editor = Livewire::test(QuotationEditor::class, ['opportunity' => $opportunity])->instance();

    if (! $editor instanceof QuotationEditor) {
        throw new LogicException('Expected a quotation editor.');
    }

    return $editor;
}

it('prices repeated price and discount edits in one pass and records one batch event', function (): void {
    $opportunity = Opportunity::factory()->create();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Sale line',
        'quantity' => '1',
        'unit_price' => 1000,
        'transaction_type' => LineItemTransactionType::Sale->value,
    ]));
    $item = $opportunity->items()->firstOrFail();
    $editor = itemPricingBatchEditor($opportunity);
    $eventsBefore = VerbEvent::query()->count();
    $auditsBefore = ActionLog::query()->count();
    DB::enableQueryLog();
    DB::flushQueryLog();

    try {
        $payload = $editor->applyFieldMutations([
            ['id' => $item->id, 'field' => 'unit_price', 'value' => '12.00'],
            ['id' => $item->id, 'field' => 'unit_price', 'value' => '15.00'],
            ['id' => $item->id, 'field' => 'discount_percent', 'value' => '10'],
        ]);
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
    }

    $linePricingWrites = collect($queries)->filter(fn (array $query): bool => str_starts_with($query['query'], 'update "opportunity_items" set')
        && str_contains($query['query'], '"total" ='));

    expect($linePricingWrites)->toHaveCount(1);
    expect($item->fresh()->unit_price)->toBe(1500);
    expect($item->fresh()->total)->toBe(1350);
    expect($item->fresh()->is_discount_manual)->toBeTrue();
    expect($payload['charge_total'])->toBe(1350);
    expect(VerbEvent::query()->count())->toBe($eventsBefore + 1);
    expect(ActionLog::query()->count())->toBe($auditsBefore + 1);
    expect(ActionLog::query()->latest('id')->firstOrFail()->action)->toBe('opportunity.item_pricing_batch_applied');
});

it('rejects an invalid later discount without persisting an earlier price edit', function (): void {
    $opportunity = Opportunity::factory()->create();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Sale line', 'quantity' => '1', 'unit_price' => 1000,
        'transaction_type' => LineItemTransactionType::Sale->value,
    ]));
    $item = $opportunity->items()->firstOrFail();
    $editor = itemPricingBatchEditor($opportunity);
    $eventCount = VerbEvent::query()->count();

    expect(fn () => $editor->applyFieldMutations([
        ['id' => $item->id, 'field' => 'unit_price', 'value' => '15.00'],
        ['id' => $item->id, 'field' => 'discount_percent', 'value' => '101'],
    ]))->toThrow(ValidationException::class);

    expect($item->fresh()->unit_price)->toBe(1000);
    expect($opportunity->fresh()->charge_total)->toBe(1000);
    expect(VerbEvent::query()->count())->toBe($eventCount);

    $editor->applyFieldMutations([
        ['id' => $item->id, 'field' => 'unit_price', 'value' => '15.00'],
        ['id' => $item->id, 'field' => 'discount_percent', 'value' => '10'],
    ]);

    expect($opportunity->fresh()->charge_total)->toBe(1350);
});

it('rejects pricing lines from another opportunity atomically', function (): void {
    $opportunity = Opportunity::factory()->create();
    $other = Opportunity::factory()->create();
    (new AddOpportunityItem)($other, AddOpportunityItemData::from([
        'name' => 'Other line', 'quantity' => '1', 'unit_price' => 1000,
        'transaction_type' => LineItemTransactionType::Sale->value,
    ]));
    $item = $other->items()->firstOrFail();
    $editor = itemPricingBatchEditor($opportunity);
    $eventCount = VerbEvent::query()->count();

    expect(fn () => $editor->applyFieldMutations([
        ['id' => $item->id, 'field' => 'unit_price', 'value' => '15.00'],
        ['id' => $item->id, 'field' => 'discount_percent', 'value' => '10'],
    ]))->toThrow(ValidationException::class);

    expect($item->fresh()->unit_price)->toBe(1000);
    expect(VerbEvent::query()->count())->toBe($eventCount);
});

it('rebuilds multi-line batch prices from the persisted batch event on replay', function (): void {
    $store = Store::factory()->create();
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Pricing replay', 'store_id' => $store->id]));
    $opportunity = Opportunity::query()->findOrFail($created->id);

    foreach (['First', 'Second'] as $name) {
        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => $name, 'quantity' => '1', 'unit_price' => 1000,
            'transaction_type' => LineItemTransactionType::Sale->value,
        ]));
    }

    $items = $opportunity->items()->orderBy('id')->get();
    $editor = itemPricingBatchEditor($opportunity);
    $editor->applyFieldMutations([
        ['id' => $items[0]->id, 'field' => 'unit_price', 'value' => '15.00'],
        ['id' => $items[1]->id, 'field' => 'unit_price', 'value' => '20.00'],
        ['id' => $items[0]->id, 'field' => 'discount_percent', 'value' => '10'],
    ]);
    $auditCount = ActionLog::query()->count();
    $before = $opportunity->items()->orderBy('id')->get(['id', 'unit_price', 'discount_percent', 'total'])->toArray();

    OpportunityItem::query()->delete();
    Verbs::replay();

    expect($opportunity->items()->orderBy('id')->get(['id', 'unit_price', 'discount_percent', 'total'])->toArray())->toBe($before);
    expect($opportunity->fresh()->charge_total)->toBe(3350);
    expect(ActionLog::query()->count())->toBe($auditCount);
});

it('resets a price to its rate while preserving zero and untouched manual overrides', function (): void {
    $opportunity = Opportunity::factory()->create();
    $product = Product::factory()->bulk()->create(['track_availability' => false]);
    $definition = RateDefinition::factory()->fixed()->create();
    ProductRate::factory()->for($product)->forSale()->create([
        'rate_definition_id' => $definition->id,
        'price' => 600,
    ]);

    foreach ([1000, 2000, 3000] as $price) {
        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => 'Rate-backed sale', 'quantity' => '1', 'unit_price' => $price,
            'itemable_id' => $product->id, 'itemable_type' => Product::class,
            'transaction_type' => LineItemTransactionType::Sale->value,
        ]));
    }

    $items = $opportunity->items()->orderBy('id')->get();
    $editor = itemPricingBatchEditor($opportunity);

    $editor->applyFieldMutations([
        ['id' => $items[0]->id, 'field' => 'unit_price', 'value' => null],
        ['id' => $items[0]->id, 'field' => 'discount_percent', 'value' => '10'],
        ['id' => $items[1]->id, 'field' => 'unit_price', 'value' => '0.00'],
    ]);

    expect($items[0]->fresh()->unit_price)->toBe(600);
    expect($items[0]->fresh()->total)->toBe(540);
    expect($items[1]->fresh()->unit_price)->toBe(0);
    expect($items[2]->fresh()->unit_price)->toBe(3000);
    expect($opportunity->fresh()->charge_total)->toBe(3540);
});

it('retains legacy events when their notification mappings are registered', function (): void {
    $opportunity = Opportunity::factory()->create();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Sale line', 'quantity' => '1', 'unit_price' => 1000,
        'transaction_type' => LineItemTransactionType::Sale->value,
    ]));
    $item = $opportunity->items()->firstOrFail();
    $editor = itemPricingBatchEditor($opportunity);
    app(EventNotificationMapRegistry::class)->register('opportunity.item_price_overridden', [['types' => []]]);
    $before = VerbEvent::query()->count();

    $editor->applyFieldMutations([
        ['id' => $item->id, 'field' => 'unit_price', 'value' => '15.00'],
        ['id' => $item->id, 'field' => 'discount_percent', 'value' => '10'],
    ]);

    expect(VerbEvent::query()->count())->toBe($before + 3);
    expect(ActionLog::query()->where('action', 'opportunity.item_pricing_batch_applied')->exists())->toBeFalse();
    expect(ActionLog::query()->where('action', 'opportunity.item_price_overridden')->exists())->toBeTrue();
    expect($opportunity->fresh()->charge_total)->toBe(1350);
});

it('rolls back projections and Verbs state when the batch pricing pass fails', function (): void {
    $opportunity = Opportunity::factory()->create();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Sale line', 'quantity' => '1', 'unit_price' => 1000,
        'transaction_type' => LineItemTransactionType::Sale->value,
    ]));
    $item = $opportunity->items()->firstOrFail();
    $editor = itemPricingBatchEditor($opportunity);
    $before = VerbEvent::query()->count();
    $calculator = app(OpportunityTotalsCalculator::class);
    $failing = Mockery::mock(OpportunityTotalsCalculator::class);
    $failing->shouldReceive('repriceOpportunity')->once()->andThrow(new RuntimeException('Pricing failed'));
    app()->instance(OpportunityTotalsCalculator::class, $failing);
    $mutations = [
        ['id' => $item->id, 'field' => 'unit_price', 'value' => '15.00'],
        ['id' => $item->id, 'field' => 'discount_percent', 'value' => '10'],
    ];

    try {
        expect(fn () => $editor->applyFieldMutations($mutations))->toThrow(RuntimeException::class, 'Pricing failed');
    } finally {
        app()->instance(OpportunityTotalsCalculator::class, $calculator);
    }

    expect($item->fresh()->unit_price)->toBe(1000);
    expect($item->fresh()->discount_percent)->toBeNull();
    expect($opportunity->fresh()->charge_total)->toBe(1000);
    expect(VerbEvent::query()->count())->toBe($before);

    $editor->applyFieldMutations($mutations);

    expect($item->fresh()->total)->toBe(1350);
    expect($opportunity->fresh()->charge_total)->toBe(1350);
});

it('does not discover unrelated item states when pricing a cold batch', function (): void {
    $opportunity = Opportunity::factory()->create();

    foreach (range(1, 10) as $number) {
        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => 'Sale '.$number, 'quantity' => '1', 'unit_price' => 1000,
            'transaction_type' => LineItemTransactionType::Sale->value,
        ]));
    }

    $item = $opportunity->items()->orderBy('id')->firstOrFail();
    $editor = itemPricingBatchEditor($opportunity);
    app(StateManager::class)->reset();
    DB::enableQueryLog();
    DB::flushQueryLog();

    try {
        $editor->applyFieldMutations([
            ['id' => $item->id, 'field' => 'unit_price', 'value' => '15.00'],
            ['id' => $item->id, 'field' => 'discount_percent', 'value' => '10'],
        ]);
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
    }

    $snapshotReads = collect($queries)->filter(fn (array $query): bool => str_starts_with($query['query'], 'select') && str_contains($query['query'], '"verb_snapshots"'));
    $eventId = VerbEvent::query()->max('id');

    expect($snapshotReads->count())->toBeLessThanOrEqual(2);
    expect(VerbStateEvent::query()->where('event_id', $eventId)->count())->toBe(1);
    expect($opportunity->fresh()->charge_total)->toBe(10350);
});

it('reconstitutes a manual price when its snapshot is missing or outdated', function (bool $missing): void {
    $opportunity = Opportunity::factory()->create();

    foreach ([1000, 2000] as $price) {
        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => 'Sale line', 'quantity' => '1', 'unit_price' => $price,
            'transaction_type' => LineItemTransactionType::Sale->value,
        ]));
    }

    $items = $opportunity->items()->orderBy('id')->get();
    $editor = itemPricingBatchEditor($opportunity);
    $snapshot = VerbSnapshot::query()->where('state_id', $items[1]->state_id);

    if ($missing) {
        $snapshot->delete();
    } else {
        $snapshot->update(['last_event_id' => 0]);
    }
    app(StateManager::class)->reset();

    $editor->applyFieldMutations([
        ['id' => $items[0]->id, 'field' => 'unit_price', 'value' => '15.00'],
        ['id' => $items[0]->id, 'field' => 'discount_percent', 'value' => '10'],
    ]);

    expect($items[1]->fresh()->unit_price)->toBe(2000);
    expect($opportunity->fresh()->charge_total)->toBe(3350);
})->with(['missing snapshot' => true, 'outdated snapshot' => false]);
