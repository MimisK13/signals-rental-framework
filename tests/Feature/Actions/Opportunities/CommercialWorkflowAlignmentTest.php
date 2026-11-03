<?php

use App\Domain\Availability\Enums\DemandPhase;
use App\Domain\Availability\Models\Demand;
use App\Domain\Products\Models\Product;
use App\Domain\Projects\Opportunities\Actions\AcceptVersion;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityItem;
use App\Domain\Projects\Opportunities\Actions\ChangeItemReservation;
use App\Domain\Projects\Opportunities\Actions\ConvertToOrder;
use App\Domain\Projects\Opportunities\Actions\ConvertToQuotation;
use App\Domain\Projects\Opportunities\Actions\CreateOpportunity;
use App\Domain\Projects\Opportunities\Actions\CreateVersion;
use App\Domain\Projects\Opportunities\Actions\DeclineVersion;
use App\Domain\Projects\Opportunities\Actions\SendVersion;
use App\Domain\Projects\Opportunities\Data\AddOpportunityItemData;
use App\Domain\Projects\Opportunities\Data\ChangeItemReservationData;
use App\Domain\Projects\Opportunities\Data\CreateOpportunityData;
use App\Domain\Projects\Opportunities\Data\CreateVersionData;
use App\Domain\Projects\Opportunities\Enums\OpportunityState;
use App\Domain\Projects\Opportunities\Enums\OpportunityStatus;
use App\Domain\Projects\Opportunities\Enums\VersionStatus;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Models\OpportunityVersion;
use App\Domain\Stores\Models\Store;
use App\Enums\LineItemTransactionType;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

/**
 * @return array{opportunity: Opportunity, item: OpportunityItem, version: OpportunityVersion}
 */
function makeCommercialAlignmentQuotation(): array
{
    $store = Store::factory()->create();
    $product = Product::factory()->rental()->bulk()->create();
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Commercial workflow alignment',
        'store_id' => $store->id,
        'starts_at' => '2026-09-01T09:00:00Z',
        'ends_at' => '2026-09-03T17:00:00Z',
        'quote_invalid_at' => '2026-08-25T23:59:00Z',
    ]));

    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '10',
        'transaction_type' => LineItemTransactionType::Rental->value,
        'unit_price' => 1000,
    ]));

    (new ConvertToQuotation)($opportunity->refresh());
    $opportunity->refresh();

    $version = (new CreateVersion)($opportunity, CreateVersionData::from([]));

    return [
        'opportunity' => $opportunity->refresh(),
        'item' => OpportunityItem::query()->where('version_id', $version->id)->sole(),
        'version' => OpportunityVersion::query()->whereKey($version->id)->firstOrFail(),
    ];
}

it('marks a version sent without reserving or activating capacity', function (): void {
    ['opportunity' => $opportunity, 'item' => $item, 'version' => $version] = makeCommercialAlignmentQuotation();

    (new SendVersion)($version->refresh(), sentVia: 'manual');

    expect($version->refresh()->status)->toBe(VersionStatus::Sent)
        ->and($opportunity->refresh()->statusEnum())->toBe(OpportunityStatus::QuotationProvisional)
        ->and($opportunity->statusEnum()->phase())->toBe(DemandPhase::Draft)
        ->and($item->refresh()->reserved_quantity)->toBeNull()
        ->and(Demand::query()->where('source_id', $item->id)->where('is_active', true)->exists())->toBeFalse();
});

it('keeps a partial reservation unchanged when a version is sent', function (): void {
    ['opportunity' => $opportunity, 'item' => $item, 'version' => $version] = makeCommercialAlignmentQuotation();

    (new ChangeItemReservation)($item, ChangeItemReservationData::from(['reserved_quantity' => '6']));
    (new SendVersion)($version->refresh(), sentVia: 'manual');

    expect($version->refresh()->status)->toBe(VersionStatus::Sent)
        ->and($item->refresh()->reserved_quantity)->toBe('6.00')
        ->and($opportunity->refresh()->statusEnum())->toBe(OpportunityStatus::QuotationProvisional)
        ->and(Demand::query()->where('source_id', $item->id)->whereNull('asset_id')->sole()->quantity)->toBe(6);
});

it('records acceptance separately from quotation to order conversion', function (): void {
    ['opportunity' => $opportunity, 'version' => $version] = makeCommercialAlignmentQuotation();

    (new SendVersion)($version->refresh(), sentVia: 'manual');
    (new AcceptVersion)($version->refresh());

    expect($version->refresh()->status)->toBe(VersionStatus::Accepted)
        ->and($opportunity->refresh()->state)->toBe(OpportunityState::Quotation)
        ->and($opportunity->statusEnum())->toBe(OpportunityStatus::QuotationProvisional);

    (new ConvertToOrder)($opportunity->refresh());

    expect($opportunity->refresh()->state)->toBe(OpportunityState::Order)
        ->and($opportunity->statusEnum())->toBe(OpportunityStatus::OrderActive)
        ->and($version->refresh()->status)->toBe(VersionStatus::Accepted);
});

it('records decline without changing the opportunity capacity lifecycle', function (): void {
    ['opportunity' => $opportunity, 'version' => $version] = makeCommercialAlignmentQuotation();

    (new SendVersion)($version->refresh(), sentVia: 'manual');
    (new DeclineVersion)($version->refresh(), 'Customer chose another supplier.');

    expect($version->refresh()->status)->toBe(VersionStatus::Declined)
        ->and($opportunity->refresh()->state)->toBe(OpportunityState::Quotation)
        ->and($opportunity->statusEnum())->toBe(OpportunityStatus::QuotationProvisional)
        ->and($opportunity->statusEnum()->phase())->toBe(DemandPhase::Draft);
});

it('replays quotation expiry as persisted opportunity data', function (): void {
    ['opportunity' => $opportunity] = makeCommercialAlignmentQuotation();
    $opportunityId = $opportunity->id;
    $expiry = $opportunity->quote_invalid_at?->toIso8601String();

    Opportunity::query()->withTrashed()->whereKey($opportunityId)->forceDelete();
    Verbs::replay();

    expect(Opportunity::query()->whereKey($opportunityId)->firstOrFail()->quote_invalid_at?->toIso8601String())
        ->toBe($expiry);
});
