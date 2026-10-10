<?php

use App\Enums\MembershipType;
use App\Enums\TaxBuyerType;
use App\Models\Member;
use App\Models\Store;
use App\Models\TaxZone;
use App\Models\User;
use Database\Seeders\DiscountCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PriceCategorySeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaxClassSeeder;
use Database\Seeders\TaxZoneSeeder;

require_once __DIR__.'/../../Support/DemoSeederTestHelpers.php';

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);

    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(TaxZoneSeeder::class);
    $this->seed(TaxClassSeeder::class);
    $this->seed(DiscountCategorySeeder::class);
    $this->seed(PriceCategorySeeder::class);

    User::factory()->owner()->create(['email' => 'test@example.com']);

    Store::factory()->create(['is_default' => true, 'timezone' => 'UTC']);

    Member::factory()
        ->organisation()
        ->count(5)
        ->create(['tag_list' => ['demo-data']]);
});

it('assigns discount and price categories to demo organisations', function () {
    seedDemoMemberEnrichment();

    $enriched = Member::query()
        ->whereJsonContains('tag_list', 'demo-data')
        ->where('membership_type', MembershipType::Organisation)
        ->whereNotNull('discount_category_id')
        ->whereNotNull('price_category_id')
        ->count();

    expect($enriched)->toBeGreaterThanOrEqual(3);
});

it('creates five tax-variant demo members', function () {
    seedDemoMemberEnrichment();

    expect(Member::query()->where('name', 'Berlin Events GmbH')->exists())->toBeTrue()
        ->and(Member::query()->where('name', 'Amsterdam Trade BV')->exists())->toBeTrue()
        ->and(Member::query()->where('name', 'Riverside Community Trust')->exists())->toBeTrue()
        ->and(Member::query()->where('name', 'Exempt Arts Foundation')->exists())->toBeTrue()
        ->and(Member::query()->where('name', 'Pacific Events LLC')->exists())->toBeTrue();
});

it('pre-validates Berlin Events GmbH and leaves Amsterdam Trade BV pending for VIES demos', function () {
    seedDemoMemberEnrichment();

    $berlin = Member::query()->where('name', 'Berlin Events GmbH')->firstOrFail();
    $amsterdam = Member::query()->where('name', 'Amsterdam Trade BV')->firstOrFail();

    expect($berlin->tax_number_validated)->toBeTrue()
        ->and($berlin->tax_number_validated_at)->not->toBeNull()
        ->and($amsterdam->tax_number)->toBe('NL123456789B01')
        ->and($amsterdam->tax_number_validated)->toBeNull()
        ->and($amsterdam->tax_number_validated_at)->toBeNull();
});

it('sets plausible tax zones and buyer types on tax-variant members', function () {
    seedDemoMemberEnrichment();

    $charity = Member::query()->where('name', 'Riverside Community Trust')->firstOrFail();
    $exempt = Member::query()->where('name', 'Exempt Arts Foundation')->firstOrFail();
    $eu = Member::query()->where('name', 'Berlin Events GmbH')->firstOrFail();
    $euZoneId = TaxZone::query()->where('slug', 'eu_member')->value('id');

    expect($charity->buyer_type)->toBe(TaxBuyerType::Charity)
        ->and($exempt->tax_exempt)->toBeTrue()
        ->and($eu->tax_number)->toBe('DE123456789')
        ->and($eu->tax_zone_id)->toBe($euZoneId);
});

it('assigns tax zones and GB country codes to demo stores', function () {
    Store::factory()->create([
        'name' => 'London Warehouse',
        'tag_list' => ['demo-data'],
        'country_code' => null,
        'tax_zone_id' => null,
    ]);

    seedDemoMemberEnrichment();

    $store = Store::query()->where('name', 'London Warehouse')->firstOrFail();
    $ukZoneId = TaxZone::query()->where('slug', 'uk')->value('id');

    expect($store->tax_zone_id)->toBe($ukZoneId)
        ->and($store->country_code)->toBe('GB');
});

it('is idempotent on re-run', function () {
    seedDemoMemberEnrichment();
    $firstCount = Member::query()->whereJsonContains('tag_list', 'demo-data')->count();

    seedDemoMemberEnrichment();
    $secondCount = Member::query()->whereJsonContains('tag_list', 'demo-data')->count();

    expect($secondCount)->toBe($firstCount);
});
