<?php

use App\Domain\Products\Database\Seeders\ProductGroupSeeder;
use App\Domain\Products\Models\ProductGroup;

it('seeds the audio catalogue hierarchy', function () {
    $this->seed(ProductGroupSeeder::class);

    $audio = ProductGroup::query()->where('name', 'Audio')->firstOrFail();
    $mixingDesks = ProductGroup::query()->where('name', 'Audio / Mixing Desks')->firstOrFail();
    $microphones = ProductGroup::query()->where('name', 'Audio / Microphones')->firstOrFail();

    expect($mixingDesks->parent_id)->toBe($audio->id)
        ->and($microphones->parent_id)->toBe($audio->id)
        ->and($audio->children()->count())->toBeGreaterThanOrEqual(7);
});

it('is idempotent when rerun', function () {
    $this->seed(ProductGroupSeeder::class);
    $firstCount = ProductGroup::query()->count();

    $this->seed(ProductGroupSeeder::class);

    expect(ProductGroup::query()->count())->toBe($firstCount);
});
