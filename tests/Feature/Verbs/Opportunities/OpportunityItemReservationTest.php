<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Pricing\Enums\LineItemTransactionType;
use App\Domain\Products\Models\Product;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityItem;
use App\Domain\Projects\Opportunities\Actions\ChangeItemQuantity;
use App\Domain\Projects\Opportunities\Actions\ChangeItemReservation;
use App\Domain\Projects\Opportunities\Actions\CreateOpportunity;
use App\Domain\Projects\Opportunities\Data\AddOpportunityItemData;
use App\Domain\Projects\Opportunities\Data\ChangeItemQuantityData;
use App\Domain\Projects\Opportunities\Data\ChangeItemReservationData;
use App\Domain\Projects\Opportunities\Data\CreateOpportunityData;
use App\Domain\Projects\Opportunities\Enums\OpportunityItemType;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use Thunk\Verbs\Exceptions\EventNotValid;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());

    $this->makeReservationItem = function (array $overrides = []): OpportunityItem {
        $opportunity = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Explicit reservation',
        ]));
        $product = Product::factory()->rental()->bulk()->create();

        (new AddOpportunityItem)(Opportunity::findOrFail($opportunity->id), AddOpportunityItemData::from(array_merge([
            'name' => $product->name,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'item_type' => OpportunityItemType::Product->value,
            'quantity' => '10',
            'transaction_type' => LineItemTransactionType::Rental->value,
            'unit_price' => 1000,
        ], $overrides)));

        return OpportunityItem::query()->where('opportunity_id', $opportunity->id)->sole();
    };
});

it('keeps legacy items nullable and distinguishes explicit zero from legacy state', function (): void {
    $item = ($this->makeReservationItem)();

    expect($item->reserved_quantity)->toBeNull();

    (new ChangeItemReservation)($item, ChangeItemReservationData::from(['reserved_quantity' => '0']));

    expect($item->refresh()->reserved_quantity)->toBe('0.00');
});

it('creates, changes, and explicitly releases an owned reservation', function (): void {
    $item = ($this->makeReservationItem)();

    (new ChangeItemReservation)($item, ChangeItemReservationData::from(['reserved_quantity' => '6']));
    expect($item->refresh()->reserved_quantity)->toBe('6.00');

    (new ChangeItemReservation)($item, ChangeItemReservationData::from(['reserved_quantity' => '3.25']));
    expect($item->refresh()->reserved_quantity)->toBe('3.25');

    (new ChangeItemReservation)($item, ChangeItemReservationData::from(['reserved_quantity' => '0']));
    expect($item->refresh()->reserved_quantity)->toBe('0.00');
});

it('rejects invalid reservation quantities and non-equipment lines', function (): void {
    $item = ($this->makeReservationItem)();

    expect(fn () => (new ChangeItemReservation)($item, ChangeItemReservationData::from(['reserved_quantity' => '-1'])))
        ->toThrow(EventNotValid::class);

    expect(fn () => (new ChangeItemReservation)($item, ChangeItemReservationData::from(['reserved_quantity' => '11'])))
        ->toThrow(EventNotValid::class);

    $service = ($this->makeReservationItem)([
        'itemable_id' => null,
        'itemable_type' => null,
        'item_type' => OpportunityItemType::Service->value,
        'transaction_type' => LineItemTransactionType::Service->value,
        'unit_price' => 1000,
    ]);

    expect(fn () => (new ChangeItemReservation)($service, ChangeItemReservationData::from(['reserved_quantity' => '1'])))
        ->toThrow(EventNotValid::class);

    $sale = ($this->makeReservationItem)([
        'transaction_type' => LineItemTransactionType::Sale->value,
    ]);

    expect(fn () => (new ChangeItemReservation)($sale, ChangeItemReservationData::from(['reserved_quantity' => '1'])))
        ->toThrow(EventNotValid::class);
});

it('does not allow a positive reservation for a zero-quantity item', function (): void {
    $item = ($this->makeReservationItem)(['quantity' => '0']);

    expect(fn () => (new ChangeItemReservation)($item, ChangeItemReservationData::from(['reserved_quantity' => '0.01'])))
        ->toThrow(EventNotValid::class);

    (new ChangeItemReservation)($item, ChangeItemReservationData::from(['reserved_quantity' => '0']));

    expect($item->refresh()->reserved_quantity)->toBe('0.00');
});

it('protects an explicit reservation when requested quantity changes', function (): void {
    $item = ($this->makeReservationItem)();
    (new ChangeItemReservation)($item, ChangeItemReservationData::from(['reserved_quantity' => '6']));

    (new ChangeItemQuantity)($item->refresh(), ChangeItemQuantityData::from(['quantity' => '12']));
    expect($item->refresh()->quantity)->toBe('12.00')
        ->and($item->reserved_quantity)->toBe('6.00');

    expect(fn () => (new ChangeItemQuantity)($item->refresh(), ChangeItemQuantityData::from(['quantity' => '4'])))
        ->toThrow(EventNotValid::class);
});

it('allows a requested quantity reduction after explicit release', function (): void {
    $item = ($this->makeReservationItem)();
    (new ChangeItemReservation)($item, ChangeItemReservationData::from(['reserved_quantity' => '6']));
    (new ChangeItemReservation)($item->refresh(), ChangeItemReservationData::from(['reserved_quantity' => '0']));

    (new ChangeItemQuantity)($item->refresh(), ChangeItemQuantityData::from(['quantity' => '4']));

    expect($item->refresh()->quantity)->toBe('4.00')
        ->and($item->reserved_quantity)->toBe('0.00');
});

it('replays explicit reservation state without requiring serial allocation', function (): void {
    $item = ($this->makeReservationItem)(['quantity' => '4']);
    $itemId = $item->id;
    $opportunityId = $item->opportunity_id;

    (new ChangeItemReservation)($item, ChangeItemReservationData::from(['reserved_quantity' => '4']));
    expect($item->refresh()->assets)->toHaveCount(0)
        ->and($item->reserved_quantity)->toBe('4.00');

    Opportunity::query()->whereKey($opportunityId)->withTrashed()->forceDelete();
    Verbs::replay();

    $replayed = OpportunityItem::query()->whereKey($itemId)->firstOrFail();

    expect($replayed->reserved_quantity)->toBe('4.00')
        ->and($replayed->assets)->toHaveCount(0);
});

it('replays a reservation change and explicit release as zero', function (): void {
    $item = ($this->makeReservationItem)();
    $itemId = $item->id;
    $opportunityId = $item->opportunity_id;

    (new ChangeItemReservation)($item, ChangeItemReservationData::from(['reserved_quantity' => '6']));
    (new ChangeItemReservation)($item->refresh(), ChangeItemReservationData::from(['reserved_quantity' => '3']));
    (new ChangeItemReservation)($item->refresh(), ChangeItemReservationData::from(['reserved_quantity' => '0']));

    Opportunity::query()->whereKey($opportunityId)->withTrashed()->forceDelete();
    Verbs::replay();

    expect(OpportunityItem::query()->whereKey($itemId)->firstOrFail()->reserved_quantity)->toBe('0.00');
});

it('preserves legacy null reservation state through replay', function (): void {
    $item = ($this->makeReservationItem)();
    $itemId = $item->id;
    $opportunityId = $item->opportunity_id;

    expect($item->reserved_quantity)->toBeNull();

    Opportunity::query()->whereKey($opportunityId)->withTrashed()->forceDelete();
    Verbs::replay();

    expect(OpportunityItem::query()->whereKey($itemId)->firstOrFail()->reserved_quantity)->toBeNull();
});

it('keeps external supply separate from owned reservation intent', function (): void {
    $item = ($this->makeReservationItem)();
    (new ChangeItemReservation)($item, ChangeItemReservationData::from(['reserved_quantity' => '6']));

    expect($item->refresh()->reserved_quantity)->toBe('6.00');
});
