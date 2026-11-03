<?php

declare(strict_types=1);

use App\Domain\AuditLog\Models\ActionLog;
use App\Domain\Products\Models\Product;
use App\Domain\Rates\Models\ProductRate;
use App\Domain\Rates\Models\RateDefinition;
use App\Domain\Rates\Presentation\Columns\ProductRateColumnRegistry;
use App\Domain\Rates\Presentation\Columns\RateDefinitionColumnRegistry;
use App\Services\ColumnRegistryResolver;

it('uses canonical rate identities', function (): void {
    $definition = RateDefinition::factory()->create();
    $rate = ProductRate::factory()->create();

    expect($definition->getMorphClass())->toBe(RateDefinition::class)
        ->and($rate->getMorphClass())->toBe(ProductRate::class)
        ->and(class_exists('App\\Models\\RateDefinition'))->toBeFalse()
        ->and(class_exists('App\\Domain\\Products\\Models\\ProductRate'))->toBeFalse();
});

it('canonicalizes persisted rate audit identities', function (): void {
    $definition = RateDefinition::factory()->create();
    $rate = ProductRate::factory()->create();
    ActionLog::factory()->create(['auditable_type' => 'App\\Models\\RateDefinition', 'auditable_id' => $definition->id]);
    ActionLog::factory()->create(['auditable_type' => 'App\\Domain\\Products\\Models\\ProductRate', 'auditable_id' => $rate->id]);

    $migration = require app_path('Domain/Rates/Database/Migrations/2026_11_03_000043_canonicalize_rate_identities_in_action_logs_table.php');
    $migration->up();

    expect(ActionLog::query()->where('auditable_type', RateDefinition::class)->count())->toBe(1)
        ->and(ActionLog::query()->where('auditable_type', ProductRate::class)->count())->toBe(1);
});

it('keeps product relationships and column registries canonical', function (): void {
    $product = Product::factory()->create();
    $rate = ProductRate::factory()->for($product)->create();

    expect($product->rates()->whereKey($rate->id)->first())->toBeInstanceOf(ProductRate::class)
        ->and(app(ColumnRegistryResolver::class)->resolve('product_rates'))->toBeInstanceOf(ProductRateColumnRegistry::class)
        ->and(app(ColumnRegistryResolver::class)->resolve('rate_definitions'))->toBeInstanceOf(RateDefinitionColumnRegistry::class);
});

arch('Rates presentation does not depend on Volt', function (): void {
    expect('App\\Domain\\Rates')->not->toUse('Livewire\\Volt');
});
