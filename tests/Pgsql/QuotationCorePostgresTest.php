<?php

declare(strict_types=1);

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Members\Models\Member;
use App\Domain\Quotations\Actions\AddQuotationItem;
use App\Domain\Quotations\Actions\CreateQuotation;
use App\Domain\Quotations\Actions\UpdateQuotationItem;
use App\Domain\Quotations\Data\AddQuotationItemData;
use App\Domain\Quotations\Data\CreateQuotationData;
use App\Domain\Quotations\Data\UpdateQuotationItemData;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationItem;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Stores\Models\Store;
use Tests\Concerns\UsesPostgres;
use Thunk\Verbs\Facades\Verbs;

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('replays item pricing snapshots and aggregate totals on PostgreSQL', function (): void {
    $customer = Member::factory()->organisation()->create();
    $store = Store::factory()->create();
    $result = app(CreateQuotation::class)(CreateQuotationData::from([
        'subject' => 'PostgreSQL item quotation',
        'store_id' => $store->id,
        'member_id' => $customer->id,
        'starts_at' => '2027-12-01T08:00:00Z',
        'ends_at' => '2027-12-04T08:00:00Z',
    ]));
    $quotation = Quotation::query()->findOrFail($result->id);
    $version = QuotationVersion::query()->findOrFail($quotation->active_version_id);
    $created = app(AddQuotationItem::class)($quotation, $version, AddQuotationItemData::from([
        'name' => 'PostgreSQL service',
        'item_type' => 'service',
        'quantity' => '2.00',
        'unit_price' => 1000,
    ]));
    $item = QuotationItem::query()->findOrFail($created->id);
    app(UpdateQuotationItem::class)($item, UpdateQuotationItemData::from(['is_optional' => true]));

    expect($item->refresh()->total)->toBe(6000)
        ->and($item->pricing_breakdown)->toBeArray()
        ->and($quotation->refresh()->charge_total)->toBe(0);

    $quotation->forceDelete();
    Verbs::replay();

    expect(QuotationItem::query()->findOrFail($item->id)->total)->toBe(6000)
        ->and(QuotationItem::query()->findOrFail($item->id)->is_optional)->toBeTrue()
        ->and(Quotation::query()->findOrFail($quotation->id)->charge_total)->toBe(0);
});

it('persists the quotation aggregate and cascades its canonical children on PostgreSQL', function (): void {
    $customer = Member::factory()->organisation()->create();
    $store = Store::factory()->create();

    $result = app(CreateQuotation::class)(CreateQuotationData::from([
        'subject' => 'PostgreSQL quotation',
        'store_id' => $store->id,
        'member_id' => $customer->id,
    ]));

    $quotation = Quotation::query()->with('activeVersion')->findOrFail($result->id);
    $item = QuotationItem::factory()->create([
        'quotation_id' => $quotation->id,
        'version_id' => $quotation->active_version_id,
    ]);

    expect($quotation->activeVersion)->not->toBeNull()
        ->and($item->version_id)->toBe($quotation->active_version_id);

    $quotation->forceDelete();

    expect(Quotation::withTrashed()->find($result->id))->toBeNull()
        ->and(QuotationItem::query()->find($item->id))->toBeNull();
});
