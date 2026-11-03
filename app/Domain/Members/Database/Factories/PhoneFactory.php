<?php

declare(strict_types=1);

namespace App\Domain\Members\Database\Factories;

use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\Phone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Phone>
 */
class PhoneFactory extends Factory
{
    protected $model = Phone::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phoneable_type' => Member::class,
            'phoneable_id' => Member::factory(),
            'number' => fake()->phoneNumber(),
            'country_code' => fake()->countryCode(),
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }
}
