<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\TaxZone;
use App\Models\TaxZoneMember;
use Illuminate\Database\Seeder;

class TaxZoneSeeder extends Seeder
{
    /** @var list<string> */
    private const EU_MEMBER_CODES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU',
        'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
    ];

    /** @var list<string> */
    private const EU_OTHER_CODES = [
        'CH', 'NO', 'IS', 'LI', 'AD', 'MC', 'SM', 'VA',
    ];

    public function run(): void
    {
        $uk = TaxZone::query()->updateOrCreate(
            ['slug' => 'uk'],
            [
                'name' => 'United Kingdom',
                'description' => 'UK domestic VAT jurisdiction.',
                'priority' => 100,
                'is_active' => true,
            ],
        );

        $euMember = TaxZone::query()->updateOrCreate(
            ['slug' => 'eu_member'],
            [
                'name' => 'EU Member States',
                'description' => 'Current EU member countries.',
                'priority' => 80,
                'is_active' => true,
            ],
        );

        $euOther = TaxZone::query()->updateOrCreate(
            ['slug' => 'eu_other'],
            [
                'name' => 'EU Other',
                'description' => 'European countries outside the EU customs union.',
                'priority' => 60,
                'is_active' => true,
            ],
        );

        $world = TaxZone::query()->updateOrCreate(
            ['slug' => 'world'],
            [
                'name' => 'Rest of World',
                'description' => 'All countries outside mapped European zones.',
                'priority' => 10,
                'is_active' => true,
            ],
        );

        $this->syncMembers($uk, ['GB']);
        $this->syncMembers($euMember, self::EU_MEMBER_CODES);
        $this->syncMembers($euOther, self::EU_OTHER_CODES);

        $mapped = array_merge(['GB'], self::EU_MEMBER_CODES, self::EU_OTHER_CODES);
        $this->syncWorldCatchAll($world, $mapped);
    }

    /**
     * @param  list<string>  $countryCodes
     */
    private function syncMembers(TaxZone $zone, array $countryCodes): void
    {
        foreach ($countryCodes as $countryCode) {
            TaxZoneMember::query()->updateOrCreate(
                [
                    'tax_zone_id' => $zone->id,
                    'country_code' => strtoupper($countryCode),
                ],
                [],
            );
        }
    }

    /**
     * @param  list<string>  $alreadyMapped
     */
    private function syncWorldCatchAll(TaxZone $world, array $alreadyMapped): void
    {
        $mapped = array_flip(array_map('strtoupper', $alreadyMapped));

        foreach ($this->activeCountryCodes() as $countryCode) {
            if (isset($mapped[$countryCode])) {
                continue;
            }

            TaxZoneMember::query()->updateOrCreate(
                [
                    'tax_zone_id' => $world->id,
                    'country_code' => $countryCode,
                ],
                [],
            );
        }
    }

    /**
     * @return list<string>
     */
    private function activeCountryCodes(): array
    {
        return Country::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->pluck('code')
            ->map(fn (string $code): string => strtoupper($code))
            ->all();
    }
}
