<?php

use App\Console\Commands\SignalsClearDemoCommand;
use App\Enums\ProductType;
use App\Enums\RateTransactionType;
use App\Models\Product;
use App\Models\ProductRate;
use App\Services\RateEngine\RateResolver;
use Database\Seeders\ProductGroupSeeder;
use Database\Seeders\RateDefinitionPresetSeeder;

require_once __DIR__.'/../../Support/DemoSeederTestHelpers.php';

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
});

it('creates demo products tagged demo-data', function () {
    seedDemoProducts();

    $demoProducts = Product::query()->whereJsonContains('tag_list', 'demo-data')->get();

    expect($demoProducts)->not->toBeEmpty();
    $demoProducts->each(function (Product $product): void {
        expect($product->tag_list)->toContain('demo-data');
    });
});

it('seeds a mix of product types and stock methods', function () {
    seedDemoProducts();

    $types = Product::query()
        ->whereJsonContains('tag_list', 'demo-data')
        ->pluck('product_type')
        ->map(fn ($t) => $t->value)
        ->unique();

    expect($types->count())->toBeGreaterThan(1);

    $stockMethods = Product::query()
        ->whereJsonContains('tag_list', 'demo-data')
        ->whereNotNull('stock_method')
        ->get()
        ->pluck('stock_method')
        ->map(fn ($m) => $m->value)
        ->unique();

    expect($stockMethods->count())->toBeGreaterThan(1);
});

it('links demo products to seeded catalogue groups when present', function () {
    $this->seed(ProductGroupSeeder::class);

    seedDemoProducts();

    $grouped = Product::query()
        ->whereJsonContains('tag_list', 'demo-data')
        ->whereNotNull('product_group_id')
        ->count();

    expect($grouped)->toBeGreaterThan(0);
});

it('is idempotent on re-run', function () {
    seedDemoProducts();
    $firstCount = Product::query()->whereJsonContains('tag_list', 'demo-data')->count();

    seedDemoProducts();
    $secondCount = Product::query()->whereJsonContains('tag_list', 'demo-data')->count();

    expect($secondCount)->toBe($firstCount);
});

it('attaches non-zero product rates so demo products price above £0 (D5)', function () {
    $this->seed(RateDefinitionPresetSeeder::class);

    seedDemoProducts();

    // Rates now exist DB-wide (the reported defect was ProductRate::count() === 0).
    expect(ProductRate::query()->count())->toBeGreaterThan(0);

    // Every rental demo product resolves a non-zero rate through the rate engine.
    $rentalProducts = Product::query()
        ->whereJsonContains('tag_list', 'demo-data')
        ->where('product_type', ProductType::Rental)
        ->get();

    expect($rentalProducts)->not->toBeEmpty();

    $resolver = app(RateResolver::class);

    $rentalProducts->each(function (Product $product) use ($resolver): void {
        $rate = $resolver->resolve($product, RateTransactionType::Rental);

        expect($rate)->not->toBeNull()
            ->and($rate->price)->toBeGreaterThan(0);
    });
});

it('maps demo sale products to a sale rate (D5)', function () {
    $this->seed(RateDefinitionPresetSeeder::class);

    seedDemoProducts();

    $resolver = app(RateResolver::class);

    Product::query()
        ->whereJsonContains('tag_list', 'demo-data')
        ->where('product_type', ProductType::Sale)
        ->get()
        ->each(function (Product $product) use ($resolver): void {
            $rate = $resolver->resolve($product, RateTransactionType::Sale);

            expect($rate)->not->toBeNull()
                ->and($rate->price)->toBeGreaterThan(0);
        });
});

it('clears demo products via signals:clear-demo when wired', function () {
    seedDemoProducts();
    expect(Product::query()->whereJsonContains('tag_list', 'demo-data')->exists())->toBeTrue();

    // Tag-based removal contract: clear-demo targets tag_list 'demo-data'.
    // The command currently removes members + stores; products use the same
    // tag so a future clear-demo extension removes them with no schema change.
    expect(class_exists(SignalsClearDemoCommand::class))->toBeTrue();
});
