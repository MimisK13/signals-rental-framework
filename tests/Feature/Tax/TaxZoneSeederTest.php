<?php

use App\Models\Country;
use App\Models\TaxZone;
use App\Models\TaxZoneMember;
use App\Services\Tax\TaxZoneResolver;
use Database\Seeders\TaxZoneSeeder;

beforeEach(function () {
    $this->resolver = app(TaxZoneResolver::class);

    Country::factory()->create(['code' => 'US', 'code3' => 'USA', 'name' => 'United States', 'is_active' => true]);
    Country::factory()->create(['code' => 'AU', 'code3' => 'AUS', 'name' => 'Australia', 'is_active' => true]);

    $this->seed(TaxZoneSeeder::class);
});

it('maps GB to the UK zone separately from EU members', function () {
    $uk = TaxZone::query()->where('slug', 'uk')->first();
    $euMember = TaxZone::query()->where('slug', 'eu_member')->first();

    expect($this->resolver->resolveCountryCode('GB')?->id)->toBe($uk->id)
        ->and($this->resolver->resolveCountryCode('DE')?->id)->toBe($euMember->id)
        ->and($this->resolver->resolveCountryCode('FR')?->id)->toBe($euMember->id);
});

it('maps all current EU member countries to eu_member zone', function () {
    $euMember = TaxZone::query()->where('slug', 'eu_member')->firstOrFail();
    $euCodes = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU',
        'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
    ];

    foreach ($euCodes as $code) {
        expect($this->resolver->resolveCountryCode($code)?->id)->toBe($euMember->id, "Failed for {$code}");
    }
});

it('maps non-EU European countries to eu_other zone', function () {
    $euOther = TaxZone::query()->where('slug', 'eu_other')->firstOrFail();

    expect($this->resolver->resolveCountryCode('CH')?->id)->toBe($euOther->id)
        ->and($this->resolver->resolveCountryCode('NO')?->id)->toBe($euOther->id);
});

it('maps unmapped active countries to the world zone', function () {
    $world = TaxZone::query()->where('slug', 'world')->firstOrFail();

    expect($this->resolver->resolveCountryCode('US')?->id)->toBe($world->id)
        ->and($this->resolver->resolveCountryCode('AU')?->id)->toBe($world->id);
});

it('prefers explicit member tax_zone_id over country lookup', function () {
    $custom = TaxZone::factory()->create(['name' => 'Custom', 'slug' => 'custom', 'priority' => 200]);

    $zone = $this->resolver->resolveForMember($custom->id, 'DE');

    expect($zone?->id)->toBe($custom->id);
});

it('lists country codes assigned to a zone', function () {
    $uk = TaxZone::query()->where('slug', 'uk')->firstOrFail();

    expect($this->resolver->countryCodesForZone($uk))->toContain('GB');
    expect(TaxZoneMember::query()->where('tax_zone_id', $uk->id)->where('country_code', 'GB')->exists())->toBeTrue();
});
