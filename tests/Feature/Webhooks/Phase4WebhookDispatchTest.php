<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Members\Models\Member;
use App\Domain\Products\Models\Product;
use App\Domain\PurchaseOrders\Actions\CreatePurchaseOrder;
use App\Domain\PurchaseOrders\Data\CreatePurchaseOrderData;
use App\Domain\PurchaseOrders\Models\PurchaseOrder;
use App\Domain\Stores\Models\Store;
use App\Domain\VirtualStock\Actions\CreateVirtualStockIntake;
use App\Domain\VirtualStock\Data\CreateVirtualStockIntakeData;
use App\Domain\Webhooks\Jobs\DeliverWebhook;
use App\Domain\Webhooks\Models\Webhook;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    Queue::fake();
});

describe('Phase 4 webhook dispatch', function () {
    it('fires purchase_order.created to a subscribed webhook', function () {
        Webhook::factory()->create([
            'user_id' => auth()->id(),
            'events' => ['purchase_order.created'],
            'is_active' => true,
        ]);

        $store = Store::factory()->create();
        $supplier = Member::factory()->organisation()->create();

        (new CreatePurchaseOrder)(CreatePurchaseOrderData::from([
            'supplier_id' => $supplier->id,
            'store_id' => $store->id,
            'po_type' => 'purchase',
        ]));

        Queue::assertPushed(
            DeliverWebhook::class,
            fn (DeliverWebhook $job): bool => $job->event === 'purchase_order.created',
        );

        assertActionLogged('purchase_order.created', PurchaseOrder::class, PurchaseOrder::query()->latest('id')->value('id'), auth()->id());
    });

    it('fires virtual_stock_intake.created to a subscribed webhook', function () {
        Webhook::factory()->create([
            'user_id' => auth()->id(),
            'events' => ['virtual_stock_intake.created'],
            'is_active' => true,
        ]);

        $store = Store::factory()->create();
        $product = Product::factory()->bulk()->create();

        (new CreateVirtualStockIntake)(CreateVirtualStockIntakeData::from([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'quantity' => 3,
            'available_from' => '2026-08-01T00:00:00Z',
            'available_until' => '2026-08-06T00:00:00Z',
            'unit_cost' => 1500,
        ]));

        Queue::assertPushed(
            DeliverWebhook::class,
            fn (DeliverWebhook $job): bool => $job->event === 'virtual_stock_intake.created',
        );
    });
});
