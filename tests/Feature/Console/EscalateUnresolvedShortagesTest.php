<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\DemandPhase;
use App\Enums\OpportunityState;
use App\Enums\ShortagePolicy;
use App\Events\AuditableEvent;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\NotificationTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(NotificationTypeSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

/**
 * @return array{0: Opportunity, 1: OpportunityItem}
 */
function shortConfirmedOrderNearDispatch(Store $store, int $daysUntilStart = 2): array
{
    $store->update([
        'shortage_policy' => ShortagePolicy::Allow->value,
        'shortage_dispatch_policy' => 'warn_partial',
    ]);

    $product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 5,
    ]);

    $startsAt = Carbon::now('UTC')->addDays($daysUntilStart);

    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window($startsAt->copy(), $startsAt->copy()->addDays(4))
        ->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'quantity' => 4,
            'source_type' => 'opportunity_item',
            'source_id' => 880001,
            'metadata' => [],
        ]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Escalation slice',
        'store_id' => $store->id,
        'starts_at' => $startsAt->toIso8601String(),
        'ends_at' => $startsAt->copy()->addDays(4)->toIso8601String(),
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '3',
    ]));
    (new ConvertToQuotation)($opportunity->fresh());
    (new ConvertToOrder)($opportunity->fresh());

    return [$opportunity->fresh(), $opportunity->items()->firstOrFail()];
}

it('escalates unresolved shortages within the configured threshold once', function () {
    Event::fake([AuditableEvent::class]);
    config(['shortages.escalation_days_before_dispatch' => 3]);

    $store = Store::factory()->create(['timezone' => 'UTC']);
    [, $item] = shortConfirmedOrderNearDispatch($store, daysUntilStart: 2);

    $this->artisan('shortages:escalate-unresolved')->assertSuccessful();

    Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $event): bool => $event->action === 'shortage.escalated');

    expect($item->fresh()->shortage_escalated_at)->not->toBeNull();

    Event::fake([AuditableEvent::class]);

    $this->artisan('shortages:escalate-unresolved')->assertSuccessful();

    Event::assertNotDispatched(AuditableEvent::class, fn (AuditableEvent $event): bool => $event->action === 'shortage.escalated');
});

it('skips orders outside the escalation threshold and non-order states', function () {
    Event::fake([AuditableEvent::class]);
    config(['shortages.escalation_days_before_dispatch' => 3]);

    $store = Store::factory()->create(['timezone' => 'UTC']);
    shortConfirmedOrderNearDispatch($store, daysUntilStart: 10);

    $this->artisan('shortages:escalate-unresolved')->assertSuccessful();

    Event::assertNotDispatched(AuditableEvent::class, fn (AuditableEvent $event): bool => $event->action === 'shortage.escalated');

    $quotation = Opportunity::factory()->create([
        'store_id' => $store->id,
        'state' => OpportunityState::Quotation,
        'starts_at' => now('UTC')->addDays(2),
    ]);

    $item = OpportunityItem::factory()->create([
        'opportunity_id' => $quotation->id,
        'shortage_escalated_at' => null,
    ]);

    $this->artisan('shortages:escalate-unresolved')->assertSuccessful();

    expect($item->fresh()->shortage_escalated_at)->toBeNull();
});
