<?php

declare(strict_types=1);

use App\Domain\Availability\Models\Demand;
use App\Domain\Members\Models\Member;
use App\Domain\Products\Models\Accessory;
use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\SerialisedComponent;
use App\Domain\Quotations\Actions\AddQuotationItem;
use App\Domain\Quotations\Actions\ClearQuotationDealPrice;
use App\Domain\Quotations\Actions\CreateQuotation;
use App\Domain\Quotations\Actions\ExpandQuotationProduct;
use App\Domain\Quotations\Actions\SetQuotationDealPrice;
use App\Domain\Quotations\Actions\SubstituteQuotationItem;
use App\Domain\Quotations\Actions\UpdateQuotationItem;
use App\Domain\Quotations\Data\AddQuotationItemData;
use App\Domain\Quotations\Data\CreateQuotationData;
use App\Domain\Quotations\Data\SetQuotationDealPriceData;
use App\Domain\Quotations\Data\UpdateQuotationItemData;
use App\Domain\Quotations\Enums\QuotationDealPriceMode;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationItem;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Quotations\Services\QuotationEditorReadModel;
use App\Domain\Stock\Models\StockLevel;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

/** @return array{quotation: Quotation, version: QuotationVersion, store: Store} */
function createParityQuotation(): array
{
    $store = Store::factory()->create();
    $created = app(CreateQuotation::class)(CreateQuotationData::from([
        'subject' => 'Parity quotation',
        'store_id' => $store->id,
        'member_id' => Member::factory()->organisation()->create()->id,
        'starts_at' => '2027-12-01T08:00:00Z',
        'ends_at' => '2027-12-02T08:00:00Z',
    ]));
    $quotation = Quotation::query()->findOrFail($created->id);

    return compact('quotation', 'store') + [
        'version' => QuotationVersion::query()->findOrFail($quotation->active_version_id),
    ];
}

it('applies and clears flat and proportional deal prices with exact totals', function (): void {
    ['quotation' => $quotation, 'version' => $version] = createParityQuotation();
    $first = QuotationItem::query()->findOrFail(app(AddQuotationItem::class)($quotation, $version, AddQuotationItemData::from([
        'name' => 'First', 'item_type' => 'service', 'unit_price' => 1000,
    ]))->id);
    $second = QuotationItem::query()->findOrFail(app(AddQuotationItem::class)($quotation, $version, AddQuotationItemData::from([
        'name' => 'Second', 'item_type' => 'service', 'unit_price' => 3000,
    ]))->id);

    app(SetQuotationDealPrice::class)($quotation, $version, SetQuotationDealPriceData::from([
        'deal_total' => 2000,
        'deal_price_mode' => QuotationDealPriceMode::Proportional->value,
    ]));

    expect($first->refresh()->total)->toBe(500)
        ->and($second->refresh()->total)->toBe(1500)
        ->and($quotation->refresh()->deal_total)->toBe(2000)
        ->and($quotation->charge_total)->toBe(2000);

    expect(fn () => app(UpdateQuotationItem::class)($first, UpdateQuotationItemData::from(['quantity' => '2'])))->toThrow(ValidationException::class);

    app(ClearQuotationDealPrice::class)($quotation, $version);

    expect($first->refresh()->total)->toBe(1000)
        ->and($second->refresh()->total)->toBe(3000)
        ->and($quotation->refresh()->deal_total)->toBeNull()
        ->and($quotation->charge_total)->toBe(4000);

    app(SetQuotationDealPrice::class)($quotation, $version, SetQuotationDealPriceData::from([
        'deal_total' => 2500,
        'deal_price_mode' => QuotationDealPriceMode::FlatHeadline->value,
    ]));

    expect($first->refresh()->total)->toBe(1000)
        ->and($second->refresh()->total)->toBe(3000)
        ->and($quotation->refresh()->charge_total)->toBe(2500);
});

it('replays proportional deal pricing and accepted baseline fields', function (): void {
    ['quotation' => $quotation, 'version' => $version] = createParityQuotation();
    $item = QuotationItem::query()->findOrFail(app(AddQuotationItem::class)($quotation, $version, AddQuotationItemData::from([
        'name' => 'Replay deal', 'item_type' => 'service', 'unit_price' => 4000,
    ]))->id);
    app(SetQuotationDealPrice::class)($quotation, $version, SetQuotationDealPriceData::from([
        'deal_total' => 3000,
        'deal_price_mode' => 'proportional',
    ]));

    $quotation->forceDelete();
    Verbs::replay();

    expect(Quotation::query()->findOrFail($quotation->id)->deal_total)->toBe(3000)
        ->and(Quotation::query()->findOrFail($quotation->id)->charge_total)->toBe(3000)
        ->and(QuotationItem::query()->findOrFail($item->id)->total)->toBe(3000)
        ->and(QuotationItem::query()->findOrFail($item->id)->deal_price_original_total)->toBe(4000);
});

it('expands combination components and included accessories beneath the principal', function (): void {
    ['quotation' => $quotation, 'version' => $version] = createParityQuotation();
    $kit = Product::factory()->kit()->create();
    $component = Product::factory()->create();
    $accessory = Product::factory()->create();
    SerialisedComponent::factory()->create([
        'product_id' => $kit->id,
        'component_product_id' => $component->id,
        'quantity' => 2,
    ]);
    Accessory::factory()->create([
        'product_id' => $kit->id,
        'accessory_product_id' => $accessory->id,
        'quantity' => 1,
        'included' => true,
        'zero_priced' => true,
    ]);
    $principal = QuotationItem::query()->findOrFail(app(AddQuotationItem::class)($quotation, $version, AddQuotationItemData::from([
        'name' => $kit->name,
        'itemable_id' => $kit->id,
        'itemable_type' => Product::class,
        'quantity' => '3',
    ]))->id);

    $createdIds = app(ExpandQuotationProduct::class)($quotation, $version, $principal);
    $children = QuotationItem::query()->whereIn('id', $createdIds)->orderBy('path')->get();

    expect($children)->toHaveCount(2)
        ->and($children->pluck('itemable_id')->all())->toContain($component->id, $accessory->id)
        ->and($children->firstWhere('itemable_id', $component->id)?->quantity)->toBe('6.00')
        ->and($children->every(fn (QuotationItem $item): bool => $item->parentPath() === $principal->path))->toBeTrue();
});

it('substitutes a product and preserves quantity and tree placement', function (): void {
    ['quotation' => $quotation, 'version' => $version] = createParityQuotation();
    $group = QuotationItem::query()->findOrFail(app(AddQuotationItem::class)($quotation, $version, AddQuotationItemData::from([
        'name' => 'Section', 'item_type' => 'group',
    ]))->id);
    $originalProduct = Product::factory()->create();
    $replacement = Product::factory()->create();
    $original = QuotationItem::query()->findOrFail(app(AddQuotationItem::class)($quotation, $version, AddQuotationItemData::from([
        'name' => $originalProduct->name,
        'itemable_id' => $originalProduct->id,
        'itemable_type' => Product::class,
        'quantity' => '2',
        'parent_path' => $group->path,
    ]))->id);

    $substitute = app(SubstituteQuotationItem::class)($original, $replacement);

    expect(QuotationItem::query()->find($original->id))->toBeNull()
        ->and($substitute->itemable_id)->toBe($replacement->id)
        ->and($substitute->quantity)->toBe('2.00')
        ->and($substitute->parentPath())->toBe($group->path);
});

it('previews availability without creating quotation Demand', function (): void {
    ['quotation' => $quotation, 'version' => $version, 'store' => $store] = createParityQuotation();
    $product = Product::factory()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 5,
    ]);
    app(AddQuotationItem::class)($quotation, $version, AddQuotationItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '2',
    ]));

    $row = app(QuotationEditorReadModel::class)->tree($quotation->refresh(), $version->refresh())[0];

    expect($row['status_label'])->toBe('Available')
        ->and($row['has_shortage'])->toBeFalse()
        ->and($row['availability_url'])->not->toBeNull()
        ->and(Demand::query()->count())->toBe(0);
});
