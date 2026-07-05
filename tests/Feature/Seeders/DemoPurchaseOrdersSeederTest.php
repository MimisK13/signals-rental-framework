<?php

use App\Models\Member;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Models\User;
use App\Models\VirtualStockIntake;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ProductGroupSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/../../Support/DemoSeederTestHelpers.php';

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    Queue::fake();

    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(ProductGroupSeeder::class);

    User::factory()->owner()->create(['email' => 'test@example.com']);
    Store::factory()->create(['is_default' => true, 'timezone' => 'UTC']);

    Member::factory()
        ->organisation()
        ->count(2)
        ->create(['tag_list' => ['demo-data']]);

    seedDemoProducts();
});

it('seeds draft and received sub-hire purchase orders via actions', function () {
    $eventsBefore = DB::table('verb_events')->count();

    seedDemoPurchaseOrders();

    expect(PurchaseOrder::query()->where('reference', 'DEMO-PO-0001')->exists())->toBeTrue()
        ->and(PurchaseOrder::query()->where('reference', 'DEMO-PO-0002')->exists())->toBeTrue()
        ->and(DB::table('verb_events')->count())->toBeGreaterThan($eventsBefore);
});

it('confirms virtual stock intake on the received sub-hire purchase order', function () {
    seedDemoPurchaseOrders();

    $po = PurchaseOrder::query()->where('reference', 'DEMO-PO-0002')->firstOrFail();
    $vsi = VirtualStockIntake::query()->where('purchase_order_id', $po->id)->firstOrFail();

    expect($po->status)->toBe('received')
        ->and($vsi->status)->toBe('confirmed')
        ->and($vsi->confirmed_at)->not->toBeNull();
});

it('links purchase order lines to demo catalogue products', function () {
    seedDemoPurchaseOrders();

    $product = Product::query()->where('sku', 'DEMO-STG-DECK2X1')->firstOrFail();
    $po = PurchaseOrder::query()->where('reference', 'DEMO-PO-0001')->with('items')->firstOrFail();

    expect($po->items->first()?->product_id)->toBe($product->id);
});

it('is idempotent on re-run', function () {
    seedDemoPurchaseOrders();
    $firstCount = PurchaseOrder::query()->where('reference', 'like', 'DEMO-PO-%')->count();

    seedDemoPurchaseOrders();

    expect(PurchaseOrder::query()->where('reference', 'like', 'DEMO-PO-%')->count())->toBe($firstCount);
});
