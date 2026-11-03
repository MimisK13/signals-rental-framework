<?php

declare(strict_types=1);

use App\Domain\Administration\Models\User;
use App\Domain\AuditLog\Models\ActionLog;
use App\Domain\Products\Models\Product;
use App\Domain\Rates\Models\ProductRate;
use App\Domain\Rates\Models\RateDefinition;
use App\Domain\Rates\Presentation\Columns\ProductRateColumnRegistry;
use App\Domain\Rates\Presentation\Columns\RateDefinitionColumnRegistry;
use App\Services\ColumnRegistryResolver;
use Illuminate\Database\Eloquent\Relations\Relation;

it('resolves historical rate audit references and preserves their morph keys', function (string $legacy, string $canonical): void {
    $model = $canonical::factory()->create();
    $log = ActionLog::factory()->create([
        'action' => 'rate.updated',
        'auditable_type' => $legacy,
        'auditable_id' => $model->getKey(),
    ]);

    expect($log->fresh()->auditable)->toBeInstanceOf($canonical);
    expect($model->getMorphClass())->toBe($legacy);
    expect(Relation::getMorphedModel($legacy))->toBe($canonical);
})->with([
    'rate definition' => ['App\\Models\\RateDefinition', RateDefinition::class],
    'product rate' => ['App\\Domain\\Products\\Models\\ProductRate', ProductRate::class],
]);

it('keeps product relationships and column registries canonical', function (): void {
    $product = Product::factory()->create();
    $rate = ProductRate::factory()->for($product)->create();

    expect($product->rates()->whereKey($rate->id)->first())->toBeInstanceOf(ProductRate::class);
    expect(app(ColumnRegistryResolver::class)->resolve('product_rates'))->toBeInstanceOf(ProductRateColumnRegistry::class);
    expect(app(ColumnRegistryResolver::class)->resolve('rate_definitions'))->toBeInstanceOf(RateDefinitionColumnRegistry::class);
});

it('serves the native product rates route', function (): void {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $user = User::factory()->owner()->create();
    $product = Product::factory()->create(['name' => 'Native rates product']);

    $this->actingAs($user)->withoutVite()->get(route('products.rates', $product))
        ->assertOk()
        ->assertSee('Rates');
});

arch('Rates presentation does not depend on Volt', function (): void {
    expect('App\Domain\Rates')->not->toUse('Livewire\Volt');
});
