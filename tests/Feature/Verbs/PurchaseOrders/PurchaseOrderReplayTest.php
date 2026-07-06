<?php

use App\Actions\PurchaseOrders\CancelPurchaseOrder;
use App\Actions\PurchaseOrders\CreatePurchaseOrder;
use App\Actions\PurchaseOrders\DeleteDraftPurchaseOrder;
use App\Actions\PurchaseOrders\SendPurchaseOrder;
use App\Actions\PurchaseOrders\UpdateDraftPurchaseOrder;
use App\Data\PurchaseOrders\CreatePurchaseOrderData;
use App\Data\PurchaseOrders\UpdateDraftPurchaseOrderData;
use App\Models\Member;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-04T12:00:00Z'));
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('reconstructs purchase order projections byte-identically after replay', function () {
    $store = Store::factory()->create(['timezone' => 'UTC']);
    $supplier = Member::factory()->organisation()->create();

    $created = (new CreatePurchaseOrder)(CreatePurchaseOrderData::from([
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
        'po_type' => 'purchase',
        'reference' => 'PO-REF-1',
        'lines' => [[
            'description' => 'Cable',
            'quantity' => '3.00',
            'unit_cost' => 1500,
        ]],
    ]));

    $po = PurchaseOrder::query()->findOrFail($created->id);

    (new UpdateDraftPurchaseOrder)($po, UpdateDraftPurchaseOrderData::from([
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
        'reference' => 'PO-REF-UPDATED',
        'lines' => [[
            'id' => $po->items()->first()->id,
            'description' => 'Cable (updated)',
            'quantity' => '4.00',
            'unit_cost' => 1500,
        ], [
            'description' => 'Connector',
            'quantity' => '1.00',
            'unit_cost' => 500,
        ]],
    ]));

    (new SendPurchaseOrder)($po->refresh());
    (new CancelPurchaseOrder)($po->refresh(), 'Supplier out of stock');

    $ordersBefore = PurchaseOrder::withTrashed()->orderBy('id')->get()->map(
        fn (PurchaseOrder $row): array => $row->getAttributes(),
    )->all();

    $itemsBefore = PurchaseOrderItem::query()->orderBy('id')->get()->map(
        fn (PurchaseOrderItem $row): array => $row->getAttributes(),
    )->all();

    $eventCountBeforeReplay = verbEventCount();

    PurchaseOrderItem::query()->delete();
    PurchaseOrder::withTrashed()->forceDelete();

    Verbs::replay();

    expect(verbEventCount())->toBe($eventCountBeforeReplay);

    $ordersAfter = PurchaseOrder::withTrashed()->orderBy('id')->get()->map(
        fn (PurchaseOrder $row): array => $row->getAttributes(),
    )->all();

    $itemsAfter = PurchaseOrderItem::query()->orderBy('id')->get()->map(
        fn (PurchaseOrderItem $row): array => $row->getAttributes(),
    )->all();

    expect($ordersAfter)->toBe($ordersBefore)
        ->and($itemsAfter)->toBe($itemsBefore);
});

it('keeps deleted drafts soft-deleted after projection wipe and replay', function () {
    $po = createTestPurchaseOrder();

    (new DeleteDraftPurchaseOrder)($po);

    expect(PurchaseOrder::query()->find($po->id))->toBeNull();

    $eventCountBeforeReplay = verbEventCount();

    PurchaseOrder::withTrashed()->whereKey($po->id)->forceDelete();

    Verbs::replay();

    expect(verbEventCount())->toBe($eventCountBeforeReplay)
        ->and(PurchaseOrder::withTrashed()->find($po->id)?->deleted_at)->not->toBeNull()
        ->and(PurchaseOrder::query()->find($po->id))->toBeNull();
});
