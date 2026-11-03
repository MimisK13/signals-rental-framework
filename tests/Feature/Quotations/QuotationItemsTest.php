<?php

declare(strict_types=1);

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Availability\Models\Demand;
use App\Domain\Members\Models\Member;
use App\Domain\Pricing\Enums\ChargePeriod;
use App\Domain\Pricing\Enums\LineItemTransactionType;
use App\Domain\Products\Models\Product;
use App\Domain\Quotations\Actions\AcceptQuotationVersion;
use App\Domain\Quotations\Actions\AddQuotationItem;
use App\Domain\Quotations\Actions\CreateQuotation;
use App\Domain\Quotations\Actions\CreateQuotationVersion;
use App\Domain\Quotations\Actions\RemoveQuotationItem;
use App\Domain\Quotations\Actions\RestructureQuotationItems;
use App\Domain\Quotations\Actions\UpdateQuotationItem;
use App\Domain\Quotations\Data\AcceptQuotationVersionData;
use App\Domain\Quotations\Data\AddQuotationItemData;
use App\Domain\Quotations\Data\CreateQuotationData;
use App\Domain\Quotations\Data\CreateQuotationVersionData;
use App\Domain\Quotations\Data\RestructureQuotationItemsData;
use App\Domain\Quotations\Data\UpdateQuotationItemData;
use App\Domain\Quotations\Enums\QuotationItemType;
use App\Domain\Quotations\Enums\QuotationVersionType;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationItem;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Quotations\Services\QuotationEditorReadModel;
use App\Domain\Rates\Models\ProductRate;
use App\Domain\Rates\Models\RateDefinition;
use App\Domain\Stock\Models\StockLevel;
use App\Domain\Stores\Models\Store;
use App\Domain\Tax\Models\OrganisationTaxClass;
use App\Domain\Tax\Models\ProductTaxClass;
use App\Domain\Tax\Models\TaxRate;
use App\Domain\Tax\Models\TaxRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

/** @return array{quotation: Quotation, version: QuotationVersion, store: Store} */
function createQuotationForItems(): array
{
    $customer = Member::factory()->organisation()->create();
    $store = Store::factory()->create();
    $result = app(CreateQuotation::class)(CreateQuotationData::from([
        'subject' => 'Item editor quotation',
        'store_id' => $store->id,
        'member_id' => $customer->id,
        'starts_at' => '2027-12-01T08:00:00Z',
        'ends_at' => '2027-12-04T08:00:00Z',
    ]));
    $quotation = Quotation::query()->findOrFail($result->id);

    return [
        'quotation' => $quotation,
        'version' => QuotationVersion::query()->findOrFail($quotation->active_version_id),
        'store' => $store,
    ];
}

it('adds a rate-priced product and projects editor-compatible totals', function (): void {
    ['quotation' => $quotation, 'version' => $version, 'store' => $store] = createQuotationForItems();
    $product = Product::factory()->rental()->create();
    $definition = RateDefinition::factory()->fixed()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 5,
    ]);
    ProductRate::factory()->create([
        'product_id' => $product->id,
        'rate_definition_id' => $definition->id,
        'store_id' => $store->id,
        'price' => 1000,
        'currency' => 'GBP',
    ]);

    $created = app(AddQuotationItem::class)($quotation, $version, AddQuotationItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'item_type' => QuotationItemType::Product->value,
        'quantity' => '2.00',
        'transaction_type' => LineItemTransactionType::Rental->value,
        'charge_period' => ChargePeriod::Fixed->value,
    ]));

    $item = QuotationItem::query()->findOrFail($created->id);
    $tree = app(QuotationEditorReadModel::class)->tree($quotation->refresh(), $version->refresh());
    $totals = app(QuotationEditorReadModel::class)->totals($quotation->refresh(), $version->refresh());

    expect($item->unit_price)->toBe(1000)
        ->and($item->total)->toBe(2000)
        ->and($item->rate_definition_id)->toBe($definition->id)
        ->and($item->pricing_breakdown['source'])->toBe('rate')
        ->and($version->refresh()->charge_total)->toBe(2000)
        ->and($quotation->refresh()->charge_total)->toBe(2000)
        ->and($tree)->toHaveCount(1)
        ->and($tree[0])->toMatchArray([
            'id' => $item->id,
            'item_type' => 'product',
            'quantity' => '2.00',
            'charge_total' => 2000,
            'has_shortage' => false,
            'planning' => null,
        ])
        ->and($totals['net'])->toBe(2000)
        ->and($totals['revision'])->toBeGreaterThan(1)
        ->and(Demand::query()->count())->toBe(0);
});

it('updates quantity, manual price, discount, optional state, and aggregate totals', function (): void {
    ['quotation' => $quotation, 'version' => $version] = createQuotationForItems();
    $created = app(AddQuotationItem::class)($quotation, $version, AddQuotationItemData::from([
        'name' => 'Manual service',
        'item_type' => QuotationItemType::Service->value,
        'quantity' => '2.00',
        'unit_price' => 1000,
        'transaction_type' => LineItemTransactionType::Service->value,
    ]));
    $item = QuotationItem::query()->findOrFail($created->id);

    app(UpdateQuotationItem::class)($item, UpdateQuotationItemData::from([
        'quantity' => '3.00',
        'unit_price' => 2000,
        'discount_percent' => '10.00',
    ]));

    expect($item->refresh()->total)->toBe(16200)
        ->and($item->unit_price)->toBe(2000)
        ->and($item->is_discount_manual)->toBeTrue()
        ->and($quotation->refresh()->charge_total)->toBe(16200);

    app(UpdateQuotationItem::class)($item, UpdateQuotationItemData::from(['is_optional' => true]));

    expect($item->refresh()->is_optional)->toBeTrue()
        ->and($quotation->refresh()->charge_total)->toBe(0)
        ->and($version->refresh()->charge_total)->toBe(0);
});

it('snapshots resolved product tax into line and aggregate totals', function (): void {
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
    $organisationTaxClass = OrganisationTaxClass::factory()->create();
    $productTaxClass = ProductTaxClass::factory()->create(['is_default' => true]);
    $taxRate = TaxRate::factory()->create(['rate' => '20.0000', 'is_active' => true]);
    TaxRule::factory()->create([
        'organisation_tax_class_id' => $organisationTaxClass->id,
        'product_tax_class_id' => $productTaxClass->id,
        'tax_rate_id' => $taxRate->id,
        'is_active' => true,
    ]);
    $customer = Member::factory()->organisation()->create(['sale_tax_class_id' => $organisationTaxClass->id]);
    $store = Store::factory()->create(['country_code' => null, 'tax_zone_id' => null]);
    $created = app(CreateQuotation::class)(CreateQuotationData::from([
        'subject' => 'Tax quotation',
        'store_id' => $store->id,
        'member_id' => $customer->id,
        'starts_at' => '2027-12-01T08:00:00Z',
        'ends_at' => '2027-12-02T08:00:00Z',
    ]));
    $quotation = Quotation::query()->findOrFail($created->id);
    $version = QuotationVersion::query()->findOrFail($quotation->active_version_id);
    $product = Product::factory()->sale()->create(['tax_class_id' => $productTaxClass->id]);

    $itemData = app(AddQuotationItem::class)($quotation, $version, AddQuotationItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'unit_price' => 1000,
        'transaction_type' => LineItemTransactionType::Sale->value,
        'charge_period' => ChargePeriod::Fixed->value,
    ]));

    $item = QuotationItem::query()->findOrFail($itemData->id);

    expect($item->total)->toBe(1000)
        ->and($item->tax_total)->toBe(200)
        ->and($item->gross_total)->toBe(1200)
        ->and($item->tax_rate)->toBe('20.00')
        ->and($quotation->refresh()->charge_excluding_tax_total)->toBe(1000)
        ->and($quotation->tax_total)->toBe(200)
        ->and($quotation->charge_including_tax_total)->toBe(1200);
});

it('allocates legal group, child, and accessory paths', function (): void {
    ['quotation' => $quotation, 'version' => $version] = createQuotationForItems();
    $groupData = app(AddQuotationItem::class)($quotation, $version, AddQuotationItemData::from([
        'name' => 'Audio',
        'item_type' => QuotationItemType::Group->value,
    ]));
    $group = QuotationItem::query()->findOrFail($groupData->id);
    $product = Product::factory()->create();
    $productData = app(AddQuotationItem::class)($quotation, $version, AddQuotationItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'parent_path' => $group->path,
    ]));
    $productItem = QuotationItem::query()->findOrFail($productData->id);
    $accessory = Product::factory()->create();
    $accessoryData = app(AddQuotationItem::class)($quotation, $version, AddQuotationItemData::from([
        'name' => $accessory->name,
        'itemable_id' => $accessory->id,
        'itemable_type' => Product::class,
        'item_type' => QuotationItemType::Accessory->value,
        'parent_path' => $productItem->path,
    ]));
    $accessoryItem = QuotationItem::query()->findOrFail($accessoryData->id);

    expect($group->path)->toBe('0001')
        ->and($productItem->path)->toBe('00010001')
        ->and($accessoryItem->path)->toBe('000100010001')
        ->and($accessoryItem->depth())->toBe(3);

    expect(fn () => app(AddQuotationItem::class)($quotation, $version, AddQuotationItemData::from([
        'name' => 'Invalid accessory',
        'item_type' => QuotationItemType::Accessory->value,
    ])))->toThrow(ValidationException::class);
});

it('restructures the complete tree and rejects foreign or illegal placement', function (): void {
    ['quotation' => $quotation, 'version' => $version] = createQuotationForItems();
    $group = QuotationItem::query()->findOrFail(app(AddQuotationItem::class)($quotation, $version, AddQuotationItemData::from([
        'name' => 'Lighting', 'item_type' => 'group',
    ]))->id);
    $text = QuotationItem::query()->findOrFail(app(AddQuotationItem::class)($quotation, $version, AddQuotationItemData::from([
        'name' => 'Notes', 'item_type' => 'text', 'parent_path' => $group->path,
    ]))->id);
    $service = QuotationItem::query()->findOrFail(app(AddQuotationItem::class)($quotation, $version, AddQuotationItemData::from([
        'name' => 'Engineer', 'item_type' => 'service', 'unit_price' => 1000,
    ]))->id);

    app(RestructureQuotationItems::class)($quotation, $version, RestructureQuotationItemsData::from([
        'nodes' => [
            ['id' => $service->id, 'depth' => 1],
            ['id' => $group->id, 'depth' => 1],
            ['id' => $text->id, 'depth' => 2],
        ],
    ]));

    expect($service->refresh()->path)->toBe('0001')
        ->and($group->refresh()->path)->toBe('0002')
        ->and($text->refresh()->path)->toBe('00020001');

    expect(fn () => app(RestructureQuotationItems::class)($quotation, $version, RestructureQuotationItemsData::from([
        'nodes' => [['id' => $service->id, 'depth' => 1]],
    ])))->toThrow(ValidationException::class);
});

it('removes a group and every descendant while recalculating totals', function (): void {
    ['quotation' => $quotation, 'version' => $version] = createQuotationForItems();
    $group = QuotationItem::query()->findOrFail(app(AddQuotationItem::class)($quotation, $version, AddQuotationItemData::from([
        'name' => 'Crew', 'item_type' => 'group',
    ]))->id);
    $child = QuotationItem::query()->findOrFail(app(AddQuotationItem::class)($quotation, $version, AddQuotationItemData::from([
        'name' => 'Operator', 'item_type' => 'service', 'unit_price' => 1000, 'parent_path' => $group->path,
    ]))->id);

    app(RemoveQuotationItem::class)($group);

    expect(QuotationItem::query()->find($group->id))->toBeNull()
        ->and(QuotationItem::query()->find($child->id))->toBeNull()
        ->and($quotation->refresh()->charge_total)->toBe(0);
});

it('clones item snapshots atomically into revisions and alternatives', function (): void {
    ['quotation' => $quotation, 'version' => $version] = createQuotationForItems();
    $original = QuotationItem::query()->findOrFail(app(AddQuotationItem::class)($quotation, $version, AddQuotationItemData::from([
        'name' => 'Snapshot line', 'item_type' => 'service', 'quantity' => '2', 'unit_price' => 1500,
    ]))->id);

    $created = app(CreateQuotationVersion::class)($quotation->refresh(), CreateQuotationVersionData::from([
        'version_type' => QuotationVersionType::Revision->value,
    ]));
    $revision = QuotationVersion::query()->findOrFail($created->id);
    $clone = $revision->items()->sole();

    expect($clone->id)->not->toBe($original->id)
        ->and($clone->name)->toBe($original->name)
        ->and($clone->path)->toBe($original->path)
        ->and($clone->total)->toBe($original->total)
        ->and($revision->charge_total)->toBe($version->refresh()->charge_total)
        ->and($quotation->refresh()->charge_total)->toBe($revision->charge_total);
});

it('replays item additions, updates, tree paths, removals, and aggregate totals', function (): void {
    ['quotation' => $quotation, 'version' => $version] = createQuotationForItems();
    $group = QuotationItem::query()->findOrFail(app(AddQuotationItem::class)($quotation, $version, AddQuotationItemData::from([
        'name' => 'Group', 'item_type' => 'group',
    ]))->id);
    $line = QuotationItem::query()->findOrFail(app(AddQuotationItem::class)($quotation, $version, AddQuotationItemData::from([
        'name' => 'Line', 'item_type' => 'service', 'unit_price' => 1000,
    ]))->id);
    app(UpdateQuotationItem::class)($line, UpdateQuotationItemData::from(['quantity' => '2.00']));
    app(RestructureQuotationItems::class)($quotation, $version, RestructureQuotationItemsData::from([
        'nodes' => [
            ['id' => $group->id, 'depth' => 1],
            ['id' => $line->id, 'depth' => 2],
        ],
    ]));
    $expected = [
        'line_id' => $line->id,
        'path' => $line->refresh()->path,
        'total' => $line->total,
        'quotation_total' => $quotation->refresh()->charge_total,
        'revision' => $quotation->editor_revision,
    ];
    $eventCount = DB::table('verb_events')->count();

    $quotation->forceDelete();
    Verbs::replay();

    $replayedLine = QuotationItem::query()->findOrFail($expected['line_id']);
    $replayedQuotation = Quotation::query()->findOrFail($quotation->id);

    expect(DB::table('verb_events')->count())->toBe($eventCount)
        ->and($replayedLine->path)->toBe($expected['path'])
        ->and($replayedLine->total)->toBe($expected['total'])
        ->and($replayedQuotation->charge_total)->toBe($expected['quotation_total'])
        ->and($replayedQuotation->editor_revision)->toBe($expected['revision']);
});

it('blocks item mutations after acceptance', function (): void {
    ['quotation' => $quotation, 'version' => $version] = createQuotationForItems();
    app(AddQuotationItem::class)($quotation, $version, AddQuotationItemData::from([
        'name' => 'Accepted line', 'item_type' => 'service', 'unit_price' => 1000,
    ]));
    app(AcceptQuotationVersion::class)($quotation->refresh(), $version->refresh(), AcceptQuotationVersionData::from([]));

    expect(fn () => app(AddQuotationItem::class)($quotation->refresh(), $version->refresh(), AddQuotationItemData::from([
        'name' => 'Too late', 'item_type' => 'text',
    ])))->toThrow(ValidationException::class);
});
