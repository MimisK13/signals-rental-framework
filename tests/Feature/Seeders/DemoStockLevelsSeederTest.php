<?php

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ProductGroupSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaxCategorySeeder;

require_once __DIR__.'/../../Support/DemoSeederTestHelpers.php';

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);

    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(ProductGroupSeeder::class);
    $this->seed(TaxCategorySeeder::class);

    User::factory()->owner()->create(['email' => 'test@example.com']);
    Store::factory()->create(['is_default' => true, 'timezone' => 'UTC']);

    seedDemoProducts();
});

it('seeds stock levels for most demo rental products', function () {
    seedDemoStockLevels();

    $stockedProducts = Product::query()
        ->whereJsonContains('tag_list', 'demo-data')
        ->where('sku', '!=', 'SVC-TECH')
        ->whereHas('stockLevels')
        ->count();

    expect($stockedProducts)->toBeGreaterThanOrEqual(7);
});

it('keeps deliberately scarce demo products low on stock', function () {
    seedDemoStockLevels();

    $stageDeck = Product::query()->where('sku', 'STG-DECK2X1')->firstOrFail();
    $ledScreen = Product::query()->where('sku', 'VID-LED55')->firstOrFail();

    $stageQty = StockLevel::query()->where('product_id', $stageDeck->id)->value('quantity_held');
    $ledAssets = StockLevel::query()->where('product_id', $ledScreen->id)->count();

    expect((float) $stageQty)->toBe(2.0)
        ->and($ledAssets)->toBe(1);
});

it('is idempotent on re-run', function () {
    seedDemoStockLevels();
    $firstCount = StockLevel::query()->count();

    seedDemoStockLevels();

    expect(StockLevel::query()->count())->toBe($firstCount);
});
