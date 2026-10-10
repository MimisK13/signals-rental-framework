<?php

use App\Actions\Opportunities\AcceptVersion;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ChangeItemReservation;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\CreateVersion;
use App\Actions\Opportunities\DeclineVersion;
use App\Actions\Opportunities\SendVersion;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\ChangeItemReservationData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\CreateVersionData;
use App\Enums\DemandPhase;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Enums\VersionStatus;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityVersion;
use App\Models\Product;
use App\Models\Store;
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
