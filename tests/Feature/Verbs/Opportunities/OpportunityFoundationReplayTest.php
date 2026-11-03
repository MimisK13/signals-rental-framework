<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Availability\Services\OpportunityItemPeriodResolver;
use App\Domain\Products\Models\Product;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityItem;
use App\Domain\Projects\Opportunities\Actions\ChangeItemDates;
use App\Domain\Projects\Opportunities\Actions\CreateOpportunity;
use App\Domain\Projects\Opportunities\Actions\UpdateOpportunity;
use App\Domain\Projects\Opportunities\Data\AddOpportunityItemData;
use App\Domain\Projects\Opportunities\Data\ChangeItemDatesData;
use App\Domain\Projects\Opportunities\Data\CreateOpportunityData;
use App\Domain\Projects\Opportunities\Data\UpdateOpportunityData;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Stores\Models\Store;
use App\Enums\LineItemTransactionType;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('rebuilds planning, priority, and cleared item overrides from the event stream', function () {
    $store = Store::factory()->create();
    $product = Product::factory()->rental()->bulk()->create();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Foundation replay',
        'store_id' => $store->id,
        'starts_at' => '2026-09-01T20:00:00Z',
        'ends_at' => '2026-09-03T23:00:00Z',
        'planning_starts_at' => '2026-09-01T08:00:00Z',
        'planning_ends_at' => '2026-09-04T18:00:00Z',
        'reservation_priority' => 50,
    ]));

    (new UpdateOpportunity)(
        Opportunity::findOrFail($created->id),
        UpdateOpportunityData::from([
            'planning_starts_at' => '2026-08-31T08:00:00Z',
            'planning_ends_at' => '2026-09-05T18:00:00Z',
            'reservation_priority' => 75,
        ]),
    );

    (new AddOpportunityItem)(
        Opportunity::findOrFail($created->id),
        AddOpportunityItemData::from([
            'name' => $product->name,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'quantity' => '2',
            'transaction_type' => LineItemTransactionType::Rental->value,
            'starts_at' => '2026-09-01T20:00:00Z',
            'ends_at' => '2026-09-03T23:00:00Z',
            'planning_starts_at' => '2026-08-30T08:00:00Z',
            'planning_ends_at' => '2026-09-06T18:00:00Z',
        ]),
    );

    $item = Opportunity::findOrFail($created->id)->items()->sole();

    (new ChangeItemDates)($item, ChangeItemDatesData::from([
        'planning_starts_at' => null,
        'planning_ends_at' => null,
    ]));

    $before = Opportunity::findOrFail($created->id);
    $beforeItem = $before->items()->sole();
    $expected = [
        'opportunity' => $before->only(['planning_starts_at', 'planning_ends_at', 'reservation_priority']),
        'item' => $beforeItem->only(['planning_starts_at', 'planning_ends_at']),
    ];

    Opportunity::query()->withTrashed()->forceDelete();
    expect(Opportunity::query()->withTrashed()->count())->toBe(0);

    Verbs::replay();

    $after = Opportunity::findOrFail($created->id);
    $afterItem = $after->items()->sole();

    expect([
        'opportunity' => $after->only(['planning_starts_at', 'planning_ends_at', 'reservation_priority']),
        'item' => $afterItem->only(['planning_starts_at', 'planning_ends_at']),
    ])->toEqual($expected)
        ->and($after->planning_starts_at->toIso8601String())->toBe('2026-08-31T08:00:00+00:00')
        ->and((int) $after->reservation_priority)->toBe(75)
        ->and($afterItem->planning_starts_at)->toBeNull()
        ->and($afterItem->planning_ends_at)->toBeNull();

    [$effectiveStart, $effectiveEnd] = app(OpportunityItemPeriodResolver::class)->planningPeriod($afterItem);

    expect($effectiveStart->toIso8601String())->toBe('2026-08-31T08:00:00+00:00')
        ->and($effectiveEnd->toIso8601String())->toBe('2026-09-05T18:00:00+00:00');
});
