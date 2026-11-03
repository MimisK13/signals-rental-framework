<?php

declare(strict_types=1);

use App\Domain\Stock\Stocktakes\Models\Stocktake;
use App\Domain\Stock\Stocktakes\Models\StocktakeLine;
use App\Domain\Stock\Stocktakes\Models\StocktakeObservation;
use App\Domain\Stores\Models\Store;
use App\Models\User;

it('uses only the canonical Stocktakes module model identities', function (): void {
    $stocktake = Stocktake::factory()->create();

    expect($stocktake->getMorphClass())->toBe(Stocktake::class)
        ->and(class_exists('App\\Models\\Stocktake'))->toBeFalse()
        ->and(class_exists('App\\Models\\StocktakeLine'))->toBeFalse()
        ->and(class_exists('App\\Models\\StocktakeObservation'))->toBeFalse()
        ->and(enum_exists('App\\Enums\\StocktakeStatus'))->toBeFalse()
        ->and(enum_exists('App\\Enums\\StocktakeObservationType'))->toBeFalse();
});

it('resolves Stocktakes module factories and relationships', function (): void {
    $observation = StocktakeObservation::factory()->create();
    $line = $observation->line;

    expect($line)->toBeInstanceOf(StocktakeLine::class);
    expect($line->stocktake)->toBeInstanceOf(Stocktake::class);
    expect($line->stocktake->store->stocktakes()->whereKey($line->stocktake_id)->exists())->toBeTrue();
});

it('serves the native stocktake history route with existing records', function (): void {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $user = User::factory()->owner()->create();
    $store = Store::factory()->create(['name' => 'Stocktake domain store']);
    Stocktake::factory()->create(['store_id' => $store->id]);

    $this->actingAs($user)->withoutVite()->get(route('stocktakes.index'))
        ->assertOk()->assertSee('Stocktake domain store')->assertSee('Physical observations');
});

arch('Stocktakes presentation does not depend on Volt', function (): void {
    expect('App\Domain\Stock\Stocktakes')->not->toUse('Livewire\Volt');
});
