<?php

use App\Models\Product;
use App\Models\ProductSupplierCost;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\TaxCategory;
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

it('creates supplier costs on demo products', function () {
    seedDemoProductEnrichment();

    expect(ProductSupplierCost::query()->count())->toBeGreaterThanOrEqual(3);
});

it('marks one supplier cost as preferred per product where multiple exist', function () {
    seedDemoProductEnrichment();

    $movingHead = Product::query()->where('sku', 'DEMO-LMH-BEAM230')->firstOrFail();

    $costs = ProductSupplierCost::query()
        ->where('product_id', $movingHead->id)
        ->get();

    expect($costs)->toHaveCount(2)
        ->and($costs->where('is_preferred', true))->toHaveCount(1);
});

it('assigns a reduced sale tax category to gaffer tape after deferred showcase assignment', function () {
    seedDemoProductEnrichment();
    applyDeferredDemoProductTaxCategories();

    $reduced = TaxCategory::query()->where('slug', 'reduced')->firstOrFail();
    $gaffer = Product::query()->where('sku', 'DEMO-CON-GAF50')->firstOrFail();

    expect($gaffer->sale_tax_category_id)->toBe($reduced->id);
});

it('configures a containerable serialised product with demo assets', function () {
    seedDemoProductEnrichment();

    $movingHead = Product::query()->where('sku', 'DEMO-LMH-BEAM230')->firstOrFail();

    expect($movingHead->is_containerable)->toBeTrue()
        ->and($movingHead->container_template)->not->toBeNull()
        ->and(StockLevel::query()->where('asset_number', 'DEMO-MH-001')->exists())->toBeTrue()
        ->and(StockLevel::query()->where('asset_number', 'DEMO-PAR-001')->exists())->toBeTrue();
});

it('is idempotent on re-run', function () {
    seedDemoProductEnrichment();
    $firstCount = ProductSupplierCost::query()->count();

    seedDemoProductEnrichment();

    expect(ProductSupplierCost::query()->count())->toBe($firstCount);
});
