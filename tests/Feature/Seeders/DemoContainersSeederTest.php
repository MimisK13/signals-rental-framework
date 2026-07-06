<?php

use App\Enums\ContainerStatus;
use App\Models\Container;
use App\Models\Member;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ProductGroupSeeder;
use Database\Seeders\RoleSeeder;

require_once __DIR__.'/../../Support/DemoSeederTestHelpers.php';

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);

    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(ProductGroupSeeder::class);

    User::factory()->owner()->create(['email' => 'test@example.com']);
    Store::factory()->create(['is_default' => true, 'timezone' => 'UTC']);

    Member::factory()
        ->organisation()
        ->count(2)
        ->create(['tag_list' => ['demo-data']]);

    seedDemoProducts();
    seedDemoProductEnrichment();
});

it('creates open and sealed demo containers on serialised assets', function () {
    seedDemoContainers();

    $open = Container::query()->where('name', 'Demo Open Flight Case')->first();
    $sealed = Container::query()->where('name', 'Demo Sealed Flight Case')->first();

    expect($open)->not->toBeNull()
        ->and($open->status)->toBe(ContainerStatus::Open)
        ->and($sealed)->not->toBeNull()
        ->and($sealed->status)->toBe(ContainerStatus::Sealed);
});

it('packs items into the sealed demo container', function () {
    seedDemoContainers();

    $sealed = Container::query()->where('name', 'Demo Sealed Flight Case')->firstOrFail();

    expect($sealed->activeItems()->count())->toBeGreaterThan(0);
});

it('is idempotent on re-run', function () {
    seedDemoContainers();
    $firstCount = Container::query()->where('name', 'like', 'Demo % Flight Case')->count();

    seedDemoContainers();

    expect(Container::query()->where('name', 'like', 'Demo % Flight Case')->count())->toBe($firstCount);
});
