<?php

declare(strict_types=1);

namespace App\Domain\Tax\Database\Factories;

use App\Domain\Tax\Models\TaxZone;
use App\Domain\Tax\Models\TaxZoneMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxZoneMember>
 */
final class TaxZoneMemberFactory extends Factory
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
