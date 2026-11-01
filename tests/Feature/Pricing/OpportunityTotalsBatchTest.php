<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ChangeItemQuantity;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\ChangeItemQuantityData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\LineItemTransactionType;
use App\Events\AuditableEvent;
use App\Livewire\Experimental\QuotationEditor;
use App\Models\ActionLog;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Store;
use App\Models\User;
use App\Services\Notifications\EventNotificationMapRegistry;
use App\Services\Opportunities\OpportunityTotalsBatch;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Models\VerbEvent;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

function pricingBatchEditor(Opportunity $opportunity): QuotationEditor
{
    $editor = Livewire::test(QuotationEditor::class, ['opportunity' => $opportunity])->instance();

    if (! $editor instanceof QuotationEditor) {
        throw new LogicException('Expected the experimental quotation editor.');
    }

    return $editor;
}

it('rolls up a field batch once while preserving intermediate line audit totals', function (): void {
    $opportunity = Opportunity::factory()->create();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Sale line',
        'transaction_type' => LineItemTransactionType::Sale->value,
        'quantity' => '1',
        'unit_price' => 1000,
    ]));
    $item = $opportunity->items()->firstOrFail();
    $editor = pricingBatchEditor($opportunity);
    DB::enableQueryLog();
    DB::flushQueryLog();

    try {
        $payload = $editor->applyFieldMutations([
            ['id' => $item->id, 'field' => 'quantity', 'value' => '2'],
            ['id' => $item->id, 'field' => 'unit_price', 'value' => '12.50'],
            ['id' => $item->id, 'field' => 'discount_percent', 'value' => '10'],
        ]);
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
    }

    $rollupWrites = collect($queries)->filter(fn (array $query): bool => str_starts_with($query['query'], 'update "opportunities" set')
        && str_contains($query['query'], '"charge_total"'));
    $audit = ActionLog::query()->where('auditable_id', $opportunity->id)->get()->keyBy('action');

    expect($rollupWrites)->toHaveCount(1);
    expect($item->fresh()->total)->toBe(2250);
    expect($opportunity->fresh()->charge_total)->toBe(2250);
    expect($payload['charge_total'])->toBe(2250);
    expect($audit['opportunity.item_quantity_changed']->getAttribute('new_values')['total'])->toBe(2000);
    expect($audit['opportunity.item_price_overridden']->getAttribute('new_values')['total'])->toBe(2500);
    expect($audit['opportunity.item_discount_set']->getAttribute('new_values')['total'])->toBe(2250);
});

it('rolls back a failed batch and clears both totals batching and Verbs state', function (): void {
    $opportunity = Opportunity::factory()->create();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Sale line',
        'transaction_type' => LineItemTransactionType::Sale->value,
        'quantity' => '1',
        'unit_price' => 1000,
    ]));
    $item = $opportunity->items()->firstOrFail();
    $editor = pricingBatchEditor($opportunity);
    $eventCount = VerbEvent::query()->count();
    $auditCount = ActionLog::query()->count();

    expect(fn () => $editor->applyFieldMutations([
        ['id' => $item->id, 'field' => 'quantity', 'value' => '2'],
        ['id' => $item->id, 'field' => 'unknown', 'value' => '3'],
    ]))->toThrow(ValidationException::class);

    expect((float) $item->fresh()->quantity)->toBe(1.0);
    expect($opportunity->fresh()->charge_total)->toBe(1000);
    expect(VerbEvent::query()->count())->toBe($eventCount);
    expect(ActionLog::query()->count())->toBe($auditCount);

    (new ChangeItemQuantity)($item->fresh(), ChangeItemQuantityData::from(['quantity' => '3']));

    expect($opportunity->fresh()->charge_total)->toBe(3000);
    expect((float) $item->fresh()->quantity)->toBe(3.0);
});

it('does not roll up untouched opportunities for an empty batch', function (): void {
    $opportunity = Opportunity::factory()->create(['charge_total' => 1234]);

    $result = app(OpportunityTotalsBatch::class)->run($opportunity, fn (): string => 'unchanged');

    expect($result)->toBe('unchanged');
    expect($opportunity->fresh()->charge_total)->toBe(1234);
});

it('matches sequential edits across lines including price resets and date changes', function (): void {
    $editors = [];
    $itemIds = [];

    foreach (['sequential', 'batch'] as $mode) {
        $opportunity = Opportunity::factory()->create([
            'starts_at' => '2026-10-28T00:00:00Z',
            'ends_at' => '2026-10-30T00:00:00Z',
        ]);

        foreach (['First line', 'Second line'] as $name) {
            (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
                'name' => $name,
                'transaction_type' => LineItemTransactionType::Sale->value,
                'quantity' => '1',
                'unit_price' => 1000,
            ]));
        }

        $itemIds[$mode] = $opportunity->items()->orderBy('id')->pluck('id')->all();
        $editors[$mode] = pricingBatchEditor($opportunity->fresh());
    }

    $edits = [
        [0, 'unit_price', '12.50'],
        [1, 'unit_price', '20.00'],
        [0, 'quantity', '3'],
        [1, 'discount_percent', '10'],
        [0, 'dates', ['starts_at' => '2026-10-28', 'ends_at' => '2026-10-30']],
        [1, 'unit_price', null],
    ];

    foreach ($edits as [$index, $field, $value]) {
        $editors['sequential']->updateField($itemIds['sequential'][$index], $field, $value);
    }

    $editors['batch']->applyFieldMutations(array_map(
        fn (array $edit): array => ['id' => $itemIds['batch'][$edit[0]], 'field' => $edit[1], 'value' => $edit[2]],
        $edits,
    ));

    $columns = ['quantity', 'unit_price', 'discount_percent', 'total', 'tax_rate', 'starts_at', 'ends_at'];
    $sequentialLines = OpportunityItem::query()->whereIn('id', $itemIds['sequential'])->orderBy('id')->get($columns)->toArray();
    $batchLines = OpportunityItem::query()->whereIn('id', $itemIds['batch'])->orderBy('id')->get($columns)->toArray();
    $totals = ['charge_total', 'tax_total', 'charge_including_tax_total', 'sale_charge_total'];

    expect($batchLines)->toBe($sequentialLines);
    expect($editors['batch']->opportunity->fresh()->only($totals))
        ->toBe($editors['sequential']->opportunity->fresh()->only($totals));
});

it('preserves the editor pricing freeze guard for a batch', function (array $locks): void {
    $opportunity = Opportunity::factory()->create();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Sale line',
        'transaction_type' => LineItemTransactionType::Sale->value,
        'quantity' => '1',
        'unit_price' => 1000,
    ]));
    $item = $opportunity->items()->firstOrFail();
    $opportunity->forceFill($locks)->saveQuietly();
    $editor = pricingBatchEditor($opportunity->fresh());
    $eventCount = VerbEvent::query()->count();

    expect(fn () => $editor->applyFieldMutations([
        ['id' => $item->id, 'field' => 'quantity', 'value' => '2'],
    ]))->toThrow(ValidationException::class);

    expect((float) $item->fresh()->quantity)->toBe(1.0);
    expect($opportunity->fresh()->charge_total)->toBe(1000);
    expect(VerbEvent::query()->count())->toBe($eventCount);
})->with([
    'deal headline' => [['deal_total' => 1800]],
    'locked tax' => [['tax_locked' => true]],
]);

it('replays batched edits with the same totals and without duplicating audit records', function (): void {
    $store = Store::factory()->create();
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Batch replay', 'store_id' => $store->id]));
    $opportunity = Opportunity::query()->findOrFail($created->id);
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Sale line',
        'transaction_type' => LineItemTransactionType::Sale->value,
        'quantity' => '1',
        'unit_price' => 1000,
    ]));
    $item = $opportunity->items()->firstOrFail();
    $editor = pricingBatchEditor($opportunity);
    $editor->applyFieldMutations([
        ['id' => $item->id, 'field' => 'quantity', 'value' => '2'],
        ['id' => $item->id, 'field' => 'unit_price', 'value' => '12.50'],
        ['id' => $item->id, 'field' => 'discount_percent', 'value' => '10'],
    ]);
    $auditCount = ActionLog::query()->count();

    OpportunityItem::query()->delete();
    Verbs::replay();

    expect(OpportunityItem::query()->findOrFail($item->id)->total)->toBe(2250);
    expect($opportunity->fresh()->charge_total)->toBe(2250);
    expect(ActionLog::query()->count())->toBe($auditCount);
});

it('keeps intermediate parent totals visible to configured notification mappings', function (): void {
    $opportunity = Opportunity::factory()->create();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Sale line',
        'transaction_type' => LineItemTransactionType::Sale->value,
        'quantity' => '1',
        'unit_price' => 1000,
    ]));
    $item = $opportunity->items()->firstOrFail();
    $editor = pricingBatchEditor($opportunity);
    app(EventNotificationMapRegistry::class)->register('opportunity.item_price_overridden', [[
        'types' => [],
        'when' => [['source' => 'model', 'field' => 'charge_total', 'operator' => 'eq', 'value' => 2500]],
    ]]);
    $observedTotals = [];
    Event::listen(AuditableEvent::class, function (AuditableEvent $event) use (&$observedTotals): void {
        if ($event->action === 'opportunity.item_price_overridden') {
            $observedTotals[] = $event->model->getAttribute('charge_total');
        }
    });

    $editor->applyFieldMutations([
        ['id' => $item->id, 'field' => 'quantity', 'value' => '2'],
        ['id' => $item->id, 'field' => 'unit_price', 'value' => '12.50'],
        ['id' => $item->id, 'field' => 'discount_percent', 'value' => '10'],
    ]);

    expect($observedTotals)->toBe([2500]);
    expect($opportunity->fresh()->charge_total)->toBe(2250);
});
