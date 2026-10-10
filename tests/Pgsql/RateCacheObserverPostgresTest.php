<?php

use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RateDefinition;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesPostgres;

uses(UsesPostgres::class)->group('pgsql');

function commitPgsqlObserverSetup(): void
{
    $connection = DB::connection('pgsql_testing');
    $connection->commit();
}

function resumePgsqlObserverIsolation(): void
{
    DB::connection('pgsql_testing')->beginTransaction();
}

it('flushes product rate cache after a committed transaction', function (): void {
    $product = Product::factory()->create();
    $definition = RateDefinition::factory()->create();
    commitPgsqlObserverSetup();

    $taggedCache = Mockery::mock();
    $taggedCache->shouldReceive('flush')->once();
    Cache::shouldReceive('supportsTags')->once()->andReturnTrue();
    Cache::shouldReceive('tags')->once()->with(['rate-product:'.$product->id])->andReturn($taggedCache);

    DB::transaction(function () use ($product, $definition): void {
        ProductRate::factory()->for($product)->for($definition, 'rateDefinition')->create();
    });

    resumePgsqlObserverIsolation();
});

it('does not flush product rate cache after a rolled back transaction', function (): void {
    $product = Product::factory()->create();
    $definition = RateDefinition::factory()->create();
    commitPgsqlObserverSetup();
    Cache::shouldReceive('supportsTags')->never();

    expect(fn () => DB::transaction(function () use ($product, $definition): void {
        ProductRate::factory()->for($product)->for($definition, 'rateDefinition')->create();

        throw new RuntimeException('rollback');
    }))->toThrow(RuntimeException::class, 'rollback');

    resumePgsqlObserverIsolation();
});

it('flushes all rate definition cache after a committed update', function (): void {
    $definition = RateDefinition::factory()->create();
    commitPgsqlObserverSetup();

    $taggedCache = Mockery::mock();
    $taggedCache->shouldReceive('flush')->once();
    Cache::shouldReceive('supportsTags')->once()->andReturnTrue();
    Cache::shouldReceive('tags')->once()->with(['rate-resolution'])->andReturn($taggedCache);

    DB::transaction(function () use ($definition): void {
        $definition->update(['name' => 'Updated definition']);
    });

    resumePgsqlObserverIsolation();
});

it('does not flush rate definition cache after a rolled back update', function (): void {
    $definition = RateDefinition::factory()->create();
    commitPgsqlObserverSetup();
    Cache::shouldReceive('supportsTags')->never();

    expect(fn () => DB::transaction(function () use ($definition): void {
        $definition->update(['name' => 'Rolled back definition']);

        throw new RuntimeException('rollback');
    }))->toThrow(RuntimeException::class, 'rollback');

    resumePgsqlObserverIsolation();
});
