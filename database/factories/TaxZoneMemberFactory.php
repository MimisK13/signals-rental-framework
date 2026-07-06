<?php

namespace Database\Factories;

use App\Models\TaxZone;
use App\Models\TaxZoneMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxZoneMember>
 */
class TaxZoneMemberFactory extends Factory
{
    protected $model = TaxZoneMember::class;

    public function definition(): array
    {
        return [
            'tax_zone_id' => TaxZone::factory(),
            'country_code' => strtoupper(fake()->countryCode()),
        ];
    }
}
