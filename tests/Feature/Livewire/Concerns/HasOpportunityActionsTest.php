<?php

use App\Domain\Availability\Enums\DemandPhase;
use App\Domain\Availability\Models\Demand;
use App\Domain\Availability\Shortages\Enums\ShortagePolicy;
use App\Domain\Products\Models\Product;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityItem;
use App\Domain\Projects\Opportunities\Actions\AllocateAsset;
use App\Domain\Projects\Opportunities\Actions\ChangeOpportunityStatus;
use App\Domain\Projects\Opportunities\Actions\ConvertToOrder;
use App\Domain\Projects\Opportunities\Actions\ConvertToQuotation;
use App\Domain\Projects\Opportunities\Actions\CreateOpportunity;
use App\Domain\Projects\Opportunities\Actions\DeleteOpportunity;
use App\Domain\Projects\Opportunities\Actions\DispatchAsset;
use App\Domain\Projects\Opportunities\Actions\SetDealPrice;
use App\Domain\Projects\Opportunities\Data\AddOpportunityItemData;
use App\Domain\Projects\Opportunities\Data\AllocateAssetData;
use App\Domain\Projects\Opportunities\Data\CreateOpportunityData;
use App\Domain\Projects\Opportunities\Data\DispatchAssetData;
use App\Domain\Projects\Opportunities\Data\SetDealPriceData;
use App\Domain\Projects\Opportunities\Enums\OpportunityState;
use App\Domain\Projects\Opportunities\Enums\OpportunityStatus;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Models\OpportunityItemAsset;
use App\Domain\Projects\Opportunities\Presentation\Livewire\Show;
use App\Domain\Stock\Models\StockLevel;
use App\Enums\LineItemTransactionType;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

function covLiveOpportunity(User $actor, int $storeId, string $subject = 'COV live'): Opportunity
{
    Auth::login($actor);

    $result = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => $subject,
        'store_id' => $storeId,
    ]));

    return Opportunity::query()->whereKey($result->id)->firstOrFail();
}

/**
 * @return array{0: Opportunity, 1: OpportunityItem}
 */
function covShortQuotationForGate(Store $store, ShortagePolicy $policy, User $actor): array
{
    Auth::login($actor);
    $store->update(['shortage_policy' => $policy->value]);
    $product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 5,
    ]);
    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-09-01T09:00:00Z'), Carbon::parse('2026-09-05T17:00:00Z'))
        ->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'quantity' => 4,
            'source_type' => 'opportunity_item',
            'source_id' => 970001,
            'metadata' => [],
        ]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Validation flash',
        'store_id' => $store->id,
        'starts_at' => '2026-09-01T09:00:00Z',
        'ends_at' => '2026-09-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '3',
    ]));
    (new ConvertToQuotation)($opportunity->fresh());

    return [$opportunity->fresh(), $opportunity->items()->firstOrFail()];
}

it('ignores openConvertModal and openConfirmModal calls with unknown keys', function () {
    $opportunity = Opportunity::factory()->create();

    $this->actingAs($this->owner);

    Livewire::test(Show::class, ['opportunity' => $opportunity])
        ->call('openConvertModal', 'not_a_real_key')
        ->assertSet('pendingConvertKey', null)
        ->call('openConfirmModal', 'not_a_real_key')
        ->assertSet('pendingConfirmKey', null);
});

it('returns early from confirmConvert and confirmTransition when no pending key is set', function () {
    $opportunity = Opportunity::factory()->quotation()->create();

    $this->actingAs($this->owner);

    Livewire::test(Show::class, ['opportunity' => $opportunity])
        ->call('confirmConvert')
        ->assertNotDispatched('toast')
        ->call('confirmTransition')
        ->assertNotDispatched('toast');
});

it('converts to order via the convert modal', function () {
    $opportunity = covLiveOpportunity($this->owner, $this->store->id, 'Convert to order modal');
    (new ConvertToQuotation)($opportunity);
    (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
        'name' => 'Line',
        'quantity' => '1',
        'unit_price' => 5000,
    ]));

    $this->actingAs($this->owner);

    Livewire::test(Show::class, ['opportunity' => $opportunity->fresh()])
        ->call('openConvertModal', 'convert_to_order')
        ->assertSet('pendingConvertKey', 'convert_to_order')
        ->call('confirmConvert')
        ->assertSet('pendingConvertKey', null)
        ->assertDispatched('toast', type: 'success', message: 'Converted to order');

    expect($opportunity->fresh()->state)->toBe(OpportunityState::Order);
});

it('confirms a conversion exactly once and dispatches the modal close (D2)', function () {
    $opportunity = covLiveOpportunity($this->owner, $this->store->id, 'Single confirm convert');

    $this->actingAs($this->owner);

    $component = Livewire::test(Show::class, ['opportunity' => $opportunity->fresh()])
        ->call('openConvertModal', 'convert_to_quotation')
        ->call('confirmConvert')
        // The single confirm path applies the change and tells the modal to close.
        ->assertSet('pendingConvertKey', null)
        ->assertDispatched('close-modal')
        ->assertDispatched('toast', type: 'success', message: 'Converted to quotation');

    expect($opportunity->fresh()->state)->toBe(OpportunityState::Quotation);

    // A second confirm with no pending key is a no-op (no double application, no
    // stray success toast) — the conversion confirms exactly once.
    $component->call('confirmConvert')
        ->assertNotDispatched('toast');

    expect($opportunity->fresh()->state)->toBe(OpportunityState::Quotation);
});

it('confirms reinstate, reopen, revert, and archive transitions via the shared modal', function () {
    $lost = covLiveOpportunity($this->owner, $this->store->id, 'Reinstate modal');
    (new ConvertToQuotation)($lost);
    (new ChangeOpportunityStatus)($lost->fresh(), OpportunityStatus::QuotationLost);

    $this->actingAs($this->owner);

    Livewire::test(Show::class, ['opportunity' => $lost->fresh()])
        ->call('openConfirmModal', 'reinstate')
        ->call('confirmTransition')
        ->assertDispatched('toast', type: 'success', message: 'Opportunity reinstated');

    expect($lost->fresh()->statusEnum())->toBe(OpportunityStatus::QuotationProvisional);

    $completed = covLiveOpportunity($this->owner, $this->store->id, 'Reopen modal');
    (new ConvertToQuotation)($completed);
    (new AddOpportunityItem)($completed->fresh(), AddOpportunityItemData::from([
        'name' => 'Kit',
        'quantity' => '1',
        'unit_price' => 1000,
    ]));
    (new ConvertToOrder)($completed->fresh());
    (new ChangeOpportunityStatus)($completed->fresh(), OpportunityStatus::OrderComplete);

    Livewire::test(Show::class, ['opportunity' => $completed->fresh()])
        ->call('openConfirmModal', 'reopen')
        ->call('confirmTransition')
        ->assertDispatched('toast', type: 'success', message: 'Order re-opened');

    expect($completed->fresh()->statusEnum())->toBe(OpportunityStatus::OrderActive);

    $order = covLiveOpportunity($this->owner, $this->store->id, 'Revert modal');
    (new ConvertToQuotation)($order);
    (new AddOpportunityItem)($order->fresh(), AddOpportunityItemData::from([
        'name' => 'Undispatched',
        'quantity' => '1',
        'unit_price' => 1000,
    ]));
    (new ConvertToOrder)($order->fresh());

    Livewire::test(Show::class, ['opportunity' => $order->fresh()])
        ->call('openConfirmModal', 'revert_to_quotation')
        ->call('confirmTransition')
        ->assertDispatched('toast', type: 'success', message: 'Reverted to quotation');

    expect($order->fresh()->state)->toBe(OpportunityState::Quotation);

    $quote = covLiveOpportunity($this->owner, $this->store->id, 'Revert draft modal');
    (new ConvertToQuotation)($quote);

    Livewire::test(Show::class, ['opportunity' => $quote->fresh()])
        ->call('openConfirmModal', 'revert_to_draft')
        ->call('confirmTransition')
        ->assertDispatched('toast', type: 'success', message: 'Reverted to draft');

    expect($quote->fresh()->state)->toBe(OpportunityState::Draft);

    $archiveTarget = covLiveOpportunity($this->owner, $this->store->id, 'Archive modal');

    Livewire::test(Show::class, ['opportunity' => $archiveTarget])
        ->call('openConfirmModal', 'delete')
        ->call('confirmTransition')
        ->assertRedirect(route('opportunities.index'));

    expect($archiveTarget->fresh()->trashed())->toBeTrue();
});

it('offers no statusOptions when the opportunity is closed', function () {
    $opportunity = covLiveOpportunity($this->owner, $this->store->id, 'Closed picker');
    (new ConvertToQuotation)($opportunity);
    (new ChangeOpportunityStatus)($opportunity->fresh(), OpportunityStatus::QuotationLost);

    $this->actingAs($this->owner);

    Livewire::test(Show::class, ['opportunity' => $opportunity->fresh()])
        ->assertViewHas('statusOptions', fn (array $options): bool => $options === []);
});

it('surfaces deal_price_active and dispatched verdicts in availableActions', function () {
    $withDeal = covLiveOpportunity($this->owner, $this->store->id, 'Deal blocks lock');
    (new ConvertToQuotation)($withDeal);
    (new SetDealPrice)($withDeal->fresh(), SetDealPriceData::from(['deal_total' => '50.00']));

    $this->actingAs($this->owner);

    $dealActions = collect(
        Livewire::test(Show::class, ['opportunity' => $withDeal->fresh()])->viewData('availableActions')
    )->keyBy('key');

    expect($dealActions['unlock_locks']['allowed'])->toBeFalse()
        ->and($dealActions['unlock_locks']['code'])->toBe('deal_price_active');

    $dispatched = covLiveOpportunity($this->owner, $this->store->id, 'Bulk dispatched');
    (new ConvertToQuotation)($dispatched);
    $product = Product::factory()->rental()->serialised()->create();
    (new AddOpportunityItem)($dispatched->fresh(), AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));
    (new ConvertToOrder)($dispatched->fresh());
    $item = $dispatched->fresh()->items()->firstOrFail();
    $stock = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
    ]);
    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $stock->id]));
    $row = OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->sole();
    (new DispatchAsset)($row, DispatchAssetData::from([]));

    $dispatchActions = collect(
        Livewire::test(Show::class, ['opportunity' => $dispatched->fresh()])->viewData('availableActions')
    )->keyBy('key');

    expect($dispatchActions['revert_to_quotation']['allowed'])->toBeFalse()
        ->and($dispatchActions['revert_to_quotation']['code'])->toBe('dispatched');
});

it('unlocks rates when locks are active via unlockRates', function () {
    $opportunity = covLiveOpportunity($this->owner, $this->store->id, 'Unlock modal');
    (new ConvertToQuotation)($opportunity);
    (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
        'name' => 'Line',
        'quantity' => '1',
        'unit_price' => 5000,
    ]));
    (new ConvertToOrder)($opportunity->fresh());

    expect($opportunity->fresh()->exchange_rate_locked)->toBeTrue()
        ->and($opportunity->fresh()->tax_locked)->toBeTrue();

    $this->actingAs($this->owner);

    Livewire::test(Show::class, ['opportunity' => $opportunity->fresh()])
        ->call('unlockRates');

    $opportunity->refresh();

    expect($opportunity->exchange_rate_locked)->toBeFalse()
        ->and($opportunity->tax_locked)->toBeFalse();
});

it('flashes permission errors for clone, archive, restore, and runTransition failures', function () {
    $draft = covLiveOpportunity($this->owner, $this->store->id, 'Auth guards draft');
    $archived = covLiveOpportunity($this->owner, $this->store->id, 'Auth guards archived');
    (new DeleteOpportunity)($archived);
    $archived = $archived->fresh();

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('opportunities.access', 'opportunities.view');
    $this->actingAs($viewer);

    Livewire::test(Show::class, ['opportunity' => $draft])
        ->call('cloneOpportunity')
        ->assertSee('You do not have permission to clone this opportunity.');

    Livewire::test(Show::class, ['opportunity' => $draft])
        ->call('archive')
        ->assertSee('You do not have permission to archive this opportunity.');

    Livewire::test(Show::class, ['opportunity' => $archived])
        ->call('restore')
        ->assertSee('You do not have permission to restore this opportunity.');
});

it('flashes the first validation error from runTransition', function () {
    Queue::fake();

    $salesUser = User::factory()->create();
    $salesUser->assignRole('Sales');

    [$opportunity] = covShortQuotationForGate($this->store, ShortagePolicy::Block, $salesUser);

    $this->actingAs($salesUser);

    $flashed = captureFlashedMessages(function () use ($opportunity): void {
        Livewire::test(Show::class, ['opportunity' => $opportunity])
            ->call('convertToOrder')
            ->assertSee('unresolved shortage');
    });

    expect($flashed['error'] ?? null)
        ->toContain('unresolved shortage')
        ->and($opportunity->fresh()->state)->toBe(OpportunityState::Quotation);
});

it('redirects cloneOpportunity on success', function () {
    $opportunity = covLiveOpportunity($this->owner, $this->store->id, 'Clone redirect');

    $this->actingAs($this->owner);

    Livewire::test(Show::class, ['opportunity' => $opportunity])
        ->call('cloneOpportunity')
        ->assertRedirect();

    expect(Opportunity::query()->count())->toBe(2);
});

it('flashes a permission error from runTransition when the actor lacks edit rights', function () {
    // A viewer with no opportunities.edit permission triggers the
    // AuthorizationException catch in runTransition (line 394).
    $opportunity = covLiveOpportunity($this->owner, $this->store->id, 'Auth runTransition');

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('opportunities.access', 'opportunities.view');
    $this->actingAs($viewer);

    $flashed = captureFlashedMessages(function () use ($opportunity): void {
        Livewire::test(Show::class, ['opportunity' => $opportunity])
            ->call('convertToQuotation');
    });

    expect($flashed['error'] ?? null)->toBe('You do not have permission to perform this action.')
        ->and($opportunity->fresh()->state)->toBe(OpportunityState::Draft);
});

it('confirmConvert returns early when pending key is unknown', function () {
    $opportunity = Opportunity::factory()->quotation()->create();

    $this->actingAs($this->owner);

    Livewire::test(Show::class, ['opportunity' => $opportunity])
        ->set('pendingConvertKey', 'unknown_convert_key')
        ->call('confirmConvert')
        ->assertNotDispatched('toast');
});

it('restore refreshes archived opportunities in place', function () {
    $opportunity = covLiveOpportunity($this->owner, $this->store->id, 'Restore in place');
    (new DeleteOpportunity)($opportunity);

    $this->actingAs($this->owner);

    Livewire::test(Show::class, ['opportunity' => $opportunity->fresh()])
        ->call('restore');

    expect($opportunity->fresh()->trashed())->toBeFalse();
});

it('broadcasts lifecycle changes without deal totals for standard opportunities', function () {
    $opportunity = covLiveOpportunity($this->owner, $this->store->id, 'No deal broadcast');
    (new ConvertToQuotation)($opportunity);

    $this->actingAs($this->owner);

    Livewire::test(Show::class, ['opportunity' => $opportunity->fresh()])
        ->call('changeStatus', OpportunityStatus::QuotationReserved->statusValue())
        ->assertDispatched(
            'opportunity-lifecycle-changed',
            fn (string $event, array $params): bool => ($params['hasDealPrice'] ?? null) === false
                && ($params['dealTotalRaw'] ?? null) === ''
        );
});

it('includes the formatted deal total when broadcasting a lifecycle change for a deal-priced opportunity', function () {
    // A quotation with a manual deal-total override: a successful in-place
    // transition fires dispatchOpportunityLifecycleChanged, which formats the
    // deal_total minor units to a decimal string (line 414).
    $opportunity = covLiveOpportunity($this->owner, $this->store->id, 'Deal lifecycle');
    (new ConvertToQuotation)($opportunity);
    (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
        'name' => 'Line', 'quantity' => '1', 'unit_price' => 5000,
    ]));
    (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from(['deal_total' => '321.00']));

    expect($opportunity->fresh()->deal_total)->not->toBeNull();

    $this->actingAs($this->owner);

    // Move to a different status within the quotation state: a runTransition that
    // succeeds, refreshes, and broadcasts the lifecycle change with the deal total.
    Livewire::test(Show::class, ['opportunity' => $opportunity->fresh()])
        ->call('changeStatus', OpportunityStatus::QuotationReserved->statusValue())
        ->assertDispatched(
            'opportunity-lifecycle-changed',
            fn (string $event, array $params): bool => ($params['hasDealPrice'] ?? null) === true
                && ($params['dealTotalRaw'] ?? null) === '321.00'
        );
});
