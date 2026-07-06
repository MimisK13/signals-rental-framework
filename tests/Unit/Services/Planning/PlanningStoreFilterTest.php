<?php

use App\Models\Store;
use App\Services\Planning\PlanningStoreFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->filter = app(PlanningStoreFilter::class);
});

it('resolves explicit store ids uniquely and cast to int', function () {
    $storeA = Store::factory()->create(['include_in_default_queries' => true]);
    $storeB = Store::factory()->create(['include_in_default_queries' => true]);

    expect($this->filter->resolvedStoreIds([$storeA->id, (string) $storeB->id, $storeA->id]))
        ->toBe([$storeA->id, $storeB->id]);
});

it('falls back to default-query stores when storeIds is empty', function () {
    $included = Store::factory()->create(['include_in_default_queries' => true, 'name' => 'Included']);
    Store::factory()->create(['include_in_default_queries' => false, 'name' => 'Excluded']);

    expect($this->filter->resolvedStoreIds([]))->toBe([$included->id]);
});

it('lists store names for suggestions ordered by name', function () {
    Store::factory()->create(['name' => 'Zulu Depot', 'include_in_default_queries' => true]);
    Store::factory()->create(['name' => 'Alpha Depot', 'include_in_default_queries' => true]);
    Store::factory()->create(['name' => 'Hidden Depot', 'include_in_default_queries' => false]);

    expect($this->filter->storeNameSuggestions())->toBe(['Alpha Depot', 'Zulu Depot']);
});

it('maps selected store ids to tag names and drops unknown ids', function () {
    $known = Store::factory()->create(['name' => 'Main', 'include_in_default_queries' => true]);

    expect($this->filter->selectedStoreTags([]))->toBe([])
        ->and($this->filter->selectedStoreTags([$known->id, 99999]))->toBe(['Main']);
});

it('maps tag names back to store ids and ignores unknown names', function () {
    $main = Store::factory()->create(['name' => 'Main', 'include_in_default_queries' => true]);
    $north = Store::factory()->create(['name' => 'North', 'include_in_default_queries' => true]);

    expect($this->filter->storeIdsFromTags(['Main', 'North', 'Main', 'Missing']))
        ->toBe([$main->id, $north->id])
        ->and($this->filter->storeIdsFromTags([]))->toBe([]);
});

it('returns the first resolved store as primary and zero when none exist', function () {
    Store::factory()->create(['include_in_default_queries' => false]);

    expect($this->filter->primaryStoreId([]))->toBe(0);

    $first = Store::factory()->create(['include_in_default_queries' => true]);
    $second = Store::factory()->create(['include_in_default_queries' => true]);

    expect($this->filter->primaryStoreId([$first->id]))->toBe($first->id)
        ->and($this->filter->primaryStoreId([$second->id, $first->id]))->toBe($second->id)
        ->and($this->filter->primaryStoreId([]))->toBe($first->id);
});
