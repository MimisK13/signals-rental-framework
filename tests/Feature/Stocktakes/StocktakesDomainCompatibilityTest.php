<?php

declare(strict_types=1);

use App\Domain\Activities\Models\Activity;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Stocktakes\Enums\StocktakeStatus;
use App\Domain\Stocktakes\Models\Stocktake;
use App\Domain\Stocktakes\Models\StocktakeLine;
use App\Domain\Stocktakes\Models\StocktakeObservation;
use App\Models\ActionLog;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;

it('resolves historical stocktake audit references after moving the model', function (): void {
    $stocktake = Stocktake::factory()->create();
    $log = ActionLog::factory()->create([
        'action' => 'stocktake.created',
        'auditable_type' => 'App\\Models\\Stocktake',
        'auditable_id' => $stocktake->id,
    ]);

    expect($log->fresh()->auditable)->toBeInstanceOf(Stocktake::class);
    expect($stocktake->getMorphClass())->toBe('App\\Models\\Stocktake');
    expect(Relation::getMorphedModel('App\\Models\\StocktakeLine'))->toBe(StocktakeLine::class);
    expect(Relation::getMorphedModel('App\\Models\\StocktakeObservation'))->toBe(StocktakeObservation::class);
});

it('preserves historical serialized status enums', function (): void {
    $legacy = 'App\\Enums\\StocktakeStatus:Draft';
    $value = unserialize('E:'.strlen($legacy).':"'.$legacy.'";');

    expect($value)->toBe(StocktakeStatus::Draft);
});

it('resolves domain factories while preserving the other domain registrations', function (): void {
    $observation = StocktakeObservation::factory()->create();
    $line = $observation->line;

    expect($line)->toBeInstanceOf(StocktakeLine::class);
    expect($line->stocktake)->toBeInstanceOf(Stocktake::class);
    expect($line->stocktake->store->stocktakes()->whereKey($line->stocktake_id)->exists())->toBeTrue();
    expect(Activity::factory()->create()->getMorphClass())->toBe('App\\Models\\Activity');
    $this->assertModelExists(CrewMember::factory()->create());
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
    expect('App\Domain\Stocktakes')->not->toUse('Livewire\Volt');
});
