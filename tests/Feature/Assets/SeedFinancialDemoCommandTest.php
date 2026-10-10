<?php

use App\Models\Asset;
use App\Models\AssetUsage;
use App\Models\Member;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    Store::factory()->create(['is_default' => true]);
    Member::factory()->organisation()->create();
});

it('seeds the four deterministic asset financial scenarios and is repeatable', function (): void {
    expect(Artisan::call('assets:seed-financial-demo'))->toBe(0);

    expect(Asset::query()->whereHas('currentStockLevel', fn ($query) => $query->whereIn('asset_number', [
        'M32-001',
        'M32-002',
        'M32-003',
        'M32-004',
    ]))->count())->toBe(4);

    expect(AssetUsage::query()->count())->toBeGreaterThanOrEqual(7);

    expect(Artisan::call('assets:seed-financial-demo'))->toBe(0)
        ->and(Asset::query()->whereHas('currentStockLevel', fn ($query) => $query->whereIn('asset_number', [
            'M32-001',
            'M32-002',
            'M32-003',
            'M32-004',
        ]))->count())->toBe(4);
});
